# acdh-dashboard-fetch-data

An API for accessing ACDH Dashboard data.

## Installation

* Clone the repo into the PHP-enabled web server
* Run `composer update` in the repository root

## Usage

Issue a GET request to the location you deployed the service into.

* required parameters:
    * `login` Redmine login (depending on the user account rights you can get different results)
    * `password` Redmine password
    * `format` output data format: `nerv` (see https://github.com/acdh-oeaw/network-visualization#how-to-use) or `csv`)
* optional parameters:
    * `apiBase` Redmine API base (defaults to `https://redmine.acdh.oeaw.ac.at`)
    * `skipAttributes` comma separated list of Redmine issues' attributes to be excluded from the output (defaults to `closed_on,created_on,done_ratio,due_date,ImprintParams,pid,QoS,start_date,updated_on`)
    * any parameter supported by the [Redmine's issues API](https://www.redmine.org/projects/redmine/wiki/Rest_Issues), e.g. `query_id`, `project_id`, etc. (defaults are `tracker_id=7` and `status_id=*`)
        * be aware using Redmine queries affects also the list of returned attributes (only custom fields specified in the Redmine query are returned - this is the Redmine behaviour)

Example: `https://qos.hephaistos.arz.oeaw.ac.at/?login=foo&password=bar&format=nerv&query_id=2`

## Performance

Depending on the number of matching Redmine issues it may take up to 1 minute to fetch the data (the Redmine API performance is the bottleneck).

