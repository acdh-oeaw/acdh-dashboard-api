# acdh-dashboard-fetch-data

An API for accessing ACDH Dashboard data.

## Installation

* Clone the repo into the PHP-enabled web server
* Run `composer update` in the repository root

## Usage

Issue a GET request to the location you deployed the service into.

* authentication - HTTP basic method, provide you ACDH Redmine login and password
* required parameters:
    * `format` output data format: `nerv` (see https://github.com/acdh-oeaw/network-visualization#how-to-use) or `csv`)
* optional parameters:
    * `apiBase` Redmine API base (defaults to `https://redmine.acdh.oeaw.ac.at`)
    * `skipAttributes` comma separated list of Redmine issues' attributes to be excluded from the output (defaults to `closed_on,created_on,done_ratio,due_date,ImprintParams,pid,QoS,start_date,updated_on`)
    * any parameter supported by the [Redmine's issues API](https://www.redmine.org/projects/redmine/wiki/Rest_Issues), e.g. `query_id`, `project_id`, etc. (defaults are `tracker_id=7` and `status_id=*`)
        * be aware using Redmine queries affects also the list of returned attributes (only custom fields specified in the Redmine query are returned - this is the Redmine behaviour)
    * `labelAttr` (`nerv` output format only, defaults to `subject`, can be set to an empty string) Redmine issue attribute to be used as a node value
    * `typeAttr` (`nerv` output format only, defaults to `state`, can be set to an empty string) Redmine issue attribute to be used as a node type
    * `issueSubjectResolution` (`0` or `1`, defaults to `1`) should subjects of Redmine issues denoted only with an issue id be resolved? Turning on (the default behaviour) significantly slows down the request execution (as every resource's subject has to be fetched in a separate HTTP request). When turned off issues which are referrenced from matched issues but not included in the matched issues set will have `Issue {ID}` labels instead of subject labels.

Example: `https://qos.hephaistos.arz.oeaw.ac.at/?login=foo&password=bar&format=nerv&query_id=2`

## Performance

Depending on the number of matching Redmine issues it may take up to 1 minute to fetch the data (the Redmine API performance is the bottleneck).

