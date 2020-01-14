<?php

/**
 * The MIT License
 *
 * Copyright 2020 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

require_once __DIR__ . '/vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'OPTIONS') {
    http_response_code(204);
    header('Allow: GET, OPTIONS');
    exit();
}

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="ACDH Dashboard"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required';
    exit();
}

$apiBase = filter_input(INPUT_GET, 'redmineUrl') ?? 'https://redmine.acdh.oeaw.ac.at';
$labelAttr = filter_input(INPUT_GET, 'labelAttr') ?? 'subject';
$typeAttr = filter_input(INPUT_GET, 'typeAttr') ?? 'status';
$auth = [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ?? ''];
$skipAttributes = filter_input(INPUT_GET, 'skipAttributes') ?? 'closed_on,created_on,done_ratio,due_date,ImprintParams,pid,QoS,start_date,updated_on';
$skipAttributes = explode(',', $skipAttributes);

if (!isset($_GET['tracker_id'])) {
    $_GET['tracker_id'] = 7;
}
if (!isset($_GET['status_id'])) {
    $_GET['status_id'] = '*';
}
$filters = '';
foreach ($_GET as $k => $v) {
    if (!in_array($k, ['format', 'redmineUrl', 'skipAttributes'])) {
        $filters .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
    }
}
$client = new Client(['auth' => $auth]);
$rawData = [];
$offset = 0;
do {
    $resp = $client->send(new Request('get', "$apiBase/issues.json?include=relations&limit=1000&offset=$offset$filters"));
    $dataTmp = json_decode($resp->getBody());
    $offset += count($dataTmp->issues);
    $rawData = array_merge($rawData, $dataTmp->issues);
} while (count($dataTmp->issues) > 0);

$longData = [];
foreach ($rawData as $i) {
    $id = $i->id;
    foreach ($i as $k => $v) {
        if (in_array($k, ['custom_fields', 'relations', 'id']) || !is_object($v) && empty($v) && (string) $v !== '0') {
            continue;
        }
        $type = 'attribute';
        if ($k === 'parent') {
            $v = $v->id;
            $type = 'relation';
        }
        $longData[] = (object) ['id' => $id, 'attribute' => $k, 'type' => $type, 'value' => is_object($v) ? $v->name : $v];
    }
    foreach ($i->custom_fields as $field) {
        foreach (is_array($field->value) ? $field->value : [$field->value] as $j) {
            if (!empty($j) || (string) $j === '0') {
                switch ($field->name) {
                    case 'tech_stack':
                        foreach (explode(' ', $j) as $k) {
                            $longData[] = (object) ['id' => $id, 'attribute' => $field->name, 'type' => 'attribute', 'value' => $k];
                        }
                        break;
                    default:
                        $longData[] = (object) ['id' => $id, 'attribute' => $field->name, 'type' => 'attribute', 'value' => $j];
                }
            }
        }
    }
    foreach ($i->relations as $rel) {
        $longData[] = (object) ['id' => $id, 'attribute' => $rel->relation_type, 'type' => 'relation', 'value' => $rel->issue_id == $id ? $rel->issue_to_id : $rel->issue_id];
    }
}

$format = filter_input(INPUT_GET, 'format') ?? 'csv';
if ($format === 'csv') {
    header('Content-Type: text/csv');
    echo "id,attribute,type,value\n";
    foreach ($longData as $i) {
        if (in_array($i->attribute, $skipAttributes)) {
            continue;
        }
        echo $i->id . ',"' . str_replace('"', '""', $i->attribute) . '",' . $i->type . ',' . (is_string($i->value) ? '"' . str_replace('"', '""', $i->value) . '"' : $i->value) . "\n";
    }
} elseif ($format === 'nerv') {
    $dataNerv = json_decode(json_encode(['nodes' => [], 'edges' => [], 'types' => ['nodes' => [], 'edges' => []]]));
    foreach ($longData as $i) {
        if (in_array($i->attribute, $skipAttributes)) {
            continue;
        }
        $i->id = 'n' . $i->id;
        if (!isset($dataNerv->nodes[$i->id])) {
            $dataNerv->nodes[$i->id] = (object) ['id' => $i->id, 'label' => '', 'type' => ''];
        }

        if ($i->attribute === $labelAttr) {
            $dataNerv->nodes[$i->id]->label = $i->value;
        }
        if ($i->attribute === $typeAttr) {
            $dataNerv->nodes[$i->id]->type = $i->value;
        }

        $i->valueId = 'n' . ($i->type === 'relation' ? $i->value : hash('sha1', $i->value));
        $i->attributeType = preg_replace('[^a-zA-Z0-9]', '', $i->attribute);
    }
    $edgesCount = 1;
    $skipAttributes = array_merge($skipAttributes, $labelAttr, $typeAttr);
    foreach ($longData as $i) {
        if (in_array($i->attribute, $skipAttributes)) {
            continue;
        }
        if (!isset($dataNerv->nodes[$i->valueId])) {
            $label = $i->type === 'relation' ? 'Issue ' . $i->value : $i->value;
            $type = $i->type === 'relation' ? 'issue' : 'value';
            $dataNerv->nodes[$i->valueId] = (object) ['id' => $i->valueId, 'label' => $label, 'type' => $type];
        }
        $dataNerv->edges[] = (object) ['id' => 'e' . $edgesCount, 'label' => $i->attribute, 'source' => $i->id, 'target' => $i->valueId, 'type' => $i->attributeType];
        $edgesCount++;
    }
    header('Content-Type: application/json');
    $dataNerv->nodes = array_values($dataNerv->nodes);
    echo json_encode($dataNerv, JSON_PRETTY_PRINT);
}

