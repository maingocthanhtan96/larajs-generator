<?php

namespace LaraJS\Core\Generators\Backend;

use LaraJS\Core\Generators\BaseGenerator;
use Carbon\Carbon;

class RouteGenerator extends BaseGenerator
{
    public function __construct($model)
    {
        parent::__construct();
        $this->path = config('generator.path.laravel.api_routes');

        $this->generate($model);
    }

    private function generate($model)
    {
        $now = Carbon::now();
        $pathTemplate = 'Routes/';
        $templateData = $this->serviceGenerator->get_template('api', $pathTemplate);
        $templateData = str_replace('{{MODEL_CLASS}}', $model['name'], $templateData);
        $templateData = str_replace('{{DATE}}', $now->toDateTimeString(), $templateData);
        $templateData = str_replace(
            '{{RESOURCE}}',
            $this->serviceGenerator->urlResource($model['name']),
            $templateData,
        );
        $notDelete = config('generator.not_delete.laravel.route.api');
        $templateDataReal = $this->serviceGenerator->getFile('api_routes');
        $templateDataReal = $this->serviceGenerator->replaceNotDelete(
            $notDelete['user'],
            $templateData,
            1,
            $templateDataReal,
        );
        $this->serviceFile->createFileReal($this->path, $templateDataReal);
    }
}
