<?php
return [
    'api_task/:id/[:key]'=>['admin/api/task',[],['id'=>'\d+','key'=>'[^\/]*']],
    'api_single/:id/[:key]'=>['admin/api/single',[],['id'=>'\d+','key'=>'[^\/]*']],
    'api_caiji'=>'admin/api/caiji',
];
