<?php

namespace LaraJS\Core\Generators\BackendUpdate;

use LaraJS\Core\Generators\BaseGenerator;
use Carbon\Carbon;

class MigrationUpdateGenerator extends BaseGenerator
{
    public function __construct($generator, $model, $updateFields)
    {
        parent::__construct();
        $this->path = config('generator.path.laravel.migration');

        $this->_generate($generator, $updateFields, $model);
        if (count($updateFields['changeFields']) > 0) {
            sleep(1); // sleep 1 second to create migrate run after
            $this->_generateChange($generator, $updateFields, $model);
        }
    }

    /**
     * @param $generator
     * @param $fileName
     * @return mixed
     */
    public function updateFileMigrate($generator, $fileName): mixed
    {
        $files = json_decode($generator->files, true);
        $fileMigrationUpdate = $files['migration_update'] ?? [];
        array_push($fileMigrationUpdate, $fileName);
        $files['migration_update'] = $fileMigrationUpdate;
        $generator->update([
            'files' => json_encode($files),
        ]);

        return $generator;
    }

    /**
     * @param $change
     * @param $field
     * @return bool
     */
    public function hasChange($change, $field): bool
    {
        return $change['db_type'] !== $field['db_type'] ||
            $change['options']['comment'] !== $field['options']['comment'];
    }

    /**
     * @param $change
     * @param $field
     * @return bool
     */
    public function hasChangeExtra($change, $field): bool
    {
        return $change['options']['unique'] !== $field['options']['unique'] ||
            $change['options']['index'] !== $field['options']['index'];
    }

    /**
     * @param $change
     * @param $field
     * @return string
     */
    public function updateOptionExtra($change, $field): string
    {
        $tableChangeExtra = '';
        $type = '';
        if ($change['options']['unique'] !== $field['options']['unique']) {
            $type = '';
            if ($change['options']['unique']) {
                $type = 'unique';
            }
            if ($field['options']['unique']) {
                $type = 'dropUnique';
            }
        }
        if ($change['options']['index'] !== $field['options']['index']) {
            if ($change['options']['index']) {
                $type = 'index';
            } else {
                if ($field['options']['index']) {
                    $type = 'dropIndex';
                }
            }
        }
        if ($type) {
            $tableChangeExtra = '$table->' . $type . '(["' . $change['field_name'] . '"]);';
        }

        return $tableChangeExtra;
    }

    private function _generate($generator, $updateFields, $model)
    {
        $now = Carbon::now();
        $timeName = date('YmdHis');
        $pathTemplate = 'Databases/Migrations/';
        $templateData = $this->serviceGenerator->get_template('migrationUpdate', $pathTemplate);
        $generateFileUp = $this->_generateFieldsUp($updateFields);
        $templateData = str_replace('{{FIELDS_UP}}', $generateFileUp, $templateData);
        $templateData = str_replace(
            '{{FIELDS_DOWN}}',
            $this->_generateFieldsDown($generator, $updateFields),
            $templateData,
        );
        $templateData = str_replace('{{DATE_TIME}}', $now->toDateTimeString(), $templateData);
        $templateData = str_replace(
            '{{TABLE_NAME}}',
            $this->serviceGenerator->tableName($model['name']),
            $templateData,
        );
        $fileName =
            date('Y_m_d_His') . "_update_{$this->serviceGenerator->tableName($model['name'])}_{$timeName}_table.php";
        if ($generateFileUp) {
            $this->updateFileMigrate($generator, $this->path . $fileName);
            $this->serviceFile->createFile($this->path, $fileName, $templateData);
        }
    }

    private function _generateChange($generator, $updateFields, $model)
    {
        $now = Carbon::now();
        $timeName = date('YmdHis');
        $pathTemplate = 'Databases/Migrations/';
        $templateData = $this->serviceGenerator->get_template('migrationChange', $pathTemplate);
        $generateFieldChangeUp = $this->_generateFieldsChangeUp($generator, $updateFields);
        $templateData = str_replace('{{FIELDS_UP}}', $generateFieldChangeUp, $templateData);
        $templateData = str_replace(
            '{{FIELDS_DOWN}}',
            $this->_generateFieldsChangeDown($generator, $updateFields),
            $templateData,
        );
        $templateData = str_replace('{{DATE_TIME}}', $now->toDateTimeString(), $templateData);
        $templateData = str_replace(
            '{{TABLE_NAME}}',
            $this->serviceGenerator->tableName($model['name']),
            $templateData,
        );
        $fileName =
            date('Y_m_d_His') . "_change_{$this->serviceGenerator->tableName($model['name'])}_{$timeName}_table.php";
        if ($generateFieldChangeUp) {
            $this->updateFileMigrate($generator, $this->path . $fileName);
            $this->serviceFile->createFile($this->path, $fileName, $templateData);
        }
    }

    private function _generateFieldsUp($updateFields): string
    {
        $fieldsGenerate = [];

        $configDBType = config('generator.db_type');
        $configDefaultValue = config('generator.default_value');

        foreach ($updateFields['updateFields'] as $field) {
            $table = '';
            $afterColumn = '';
            if ($field['after_column']) {
                $afterColumn = $field['after_column'];
                $afterColumn = '->after("' . $afterColumn . '")';
            }
            foreach ($configDBType as $typeLaravel => $typeDB) {
                $migrationField = $this->serviceGenerator->migrationFields(
                    $field,
                    $configDBType,
                    $typeDB,
                    $typeLaravel,
                );
                if ($migrationField) {
                    $table = $migrationField;
                    break;
                }
            }
            $table .= $this->serviceGenerator->migrationDefaultValue($field, $configDefaultValue);
            $table .= $this->serviceGenerator->migrationOption($field);
            if ($table) {
                $table .= $afterColumn . '; // Update';
                $fieldsGenerate[] = $table;
            }
        }

        foreach ($updateFields['renameFields'] as $rename) {
            $tableRename =
                '$table->renameColumn("' .
                trim($rename['field_name_old']['field_name']) .
                '", "' .
                trim($rename['field_name_new']['field_name']) .
                '"); // Rename';
            $fieldsGenerate[] = $tableRename;
        }

        $dropFields = '';
        foreach ($updateFields['dropFields'] as $index => $drop) {
            $name = $drop['field_name'];
            if ($index === count($updateFields['dropFields']) - 1) {
                $dropFields .= "'$name'";
            } else {
                $dropFields .= "'$name',";
            }
        }
        if ($dropFields) {
            $tableDrop = '$table->dropColumn([' . $dropFields . ']); // Drop';
            $fieldsGenerate[] = $tableDrop;
        }

        return implode($this->serviceGenerator->infy_nl_tab(1, 3), $fieldsGenerate);
    }

    private function _generateFieldsDown($generator, $updateFields): string
    {
        $fieldsGenerate = [];

        $configDBType = config('generator.db_type');
        $configDefaultValue = config('generator.default_value');
        foreach ($updateFields['updateFields'] as $field) {
            $fieldsGenerate[] = '$table->dropColumn("' . trim($field['field_name']) . '"); //Drop Update';
        }

        foreach ($updateFields['renameFields'] as $rename) {
            $tableRename =
                '$table->renameColumn("' .
                trim($rename['field_name_new']['field_name']) .
                '", "' .
                trim($rename['field_name_old']['field_name']) .
                '"); // Reverse Rename';
            $fieldsGenerate[] = $tableRename;
        }

        $formFields = json_decode($generator->field, true);

        $arrayDrops = [];

        foreach ($updateFields['dropFields'] as $drop) {
            $arrayDrops[] = $drop['field_name'];
        }

        foreach ($formFields as $change) {
            if (in_array(trim($change['field_name']), $arrayDrops)) {
                $tableDrop = '';
                foreach ($configDBType as $typeLaravel => $typeDB) {
                    $migrationField = $this->serviceGenerator->migrationFields(
                        $change,
                        $configDBType,
                        $typeDB,
                        $typeLaravel,
                    );
                    if ($migrationField) {
                        $tableDrop = $migrationField;
                        break;
                    }
                }
                $tableDrop .= $this->serviceGenerator->migrationDefaultValue($change, $configDefaultValue);
                if ($tableDrop) {
                    $tableDrop .= '; // Add Drop Func Up';
                    $fieldsGenerate[] = $tableDrop;
                }
            }
        }

        return implode($this->serviceGenerator->infy_nl_tab(1, 3), $fieldsGenerate);
    }

    private function _generateFieldsChangeUp($generator, $updateFields): string
    {
        $fieldsGenerate = [];
        $formFields = json_decode($generator->field, true);

        $configDBType = config('generator.db_type');
        $configDefaultValue = config('generator.default_value');

        foreach ($updateFields['changeFields'] as $change) {
            foreach ($formFields as $field) {
                if ($change['id'] === $field['id']) {
                    if ($this->hasChange($change, $field)) {
                        $tableChange = $this->_getTableChange(
                            $configDBType,
                            $change,
                            $configDefaultValue,
                            $field['options']['comment'],
                        );
                        $tableChange .= '->change(); // Change';
                        $fieldsGenerate[] = $tableChange;
                    }
                    if ($this->hasChangeExtra($change, $field)) {
                        $tableChangeExtra = $this->updateOptionExtra($change, $field);
                        if ($tableChangeExtra) {
                            $fieldsGenerate[] = $tableChangeExtra;
                        }
                    }
                }
            }
        }

        return implode($this->serviceGenerator->infy_nl_tab(1, 3), $fieldsGenerate);
    }

    private function _generateFieldsChangeDown($generator, $updateFields): string
    {
        $fieldsGenerate = [];

        $configDBType = config('generator.db_type');
        $configDefaultValue = config('generator.default_value');

        $formFields = json_decode($generator->field, true);

        foreach ($updateFields['changeFields'] as $changeNew) {
            foreach ($formFields as $change) {
                if ($change['id'] === $changeNew['id']) {
                    if ($this->hasChange($change, $changeNew)) {
                        $tableChange = $this->_getTableChange(
                            $configDBType,
                            $change,
                            $configDefaultValue,
                            $changeNew['options']['comment'],
                        );
                        if ($tableChange) {
                            $tableChange .= '->change(); // Reverse change';
                            $fieldsGenerate[] = $tableChange;
                        }
                    }
                    if ($this->hasChangeExtra($change, $changeNew)) {
                        $tableChangeExtra = $this->updateOptionExtra($change, $changeNew);
                        if ($tableChangeExtra) {
                            $fieldsGenerate[] = $tableChangeExtra;
                        }
                    }
                }
            }
        }

        return implode($this->serviceGenerator->infy_nl_tab(1, 3), $fieldsGenerate);
    }

    /**
     * @param  mixed  $configDBType
     * @param  mixed  $change
     * @param  mixed  $configDefaultValue
     * @param $comment
     * @return string
     */
    private function _getTableChange(mixed $configDBType, mixed $change, mixed $configDefaultValue, $comment): string
    {
        $tableChange = '';
        foreach ($configDBType as $typeLaravel => $typeDB) {
            if ($change['db_type'] === $configDBType['string']) {
                $tableChange .=
                    '$table->string("' . trim($change['field_name']) . '", ' . $change['length_varchar'] . ')';
                break;
            }
            if ($change['db_type'] === $configDBType['enum']) {
                break;
            }
            if ($change['db_type'] === $typeDB) {
                $tableChange .= '$table->' . $typeLaravel . '("' . trim($change['field_name']) . '")';
                break;
            }
        }
        if ($change['default_value'] === $configDefaultValue['null']) {
            $tableChange .= '->nullable()';
        } elseif ($change['default_value'] === $configDefaultValue['as_define']) {
            $tableChange .= '->nullable()->default("' . $change['as_define'] . '")';
        } elseif ($change['default_value'] === $configDefaultValue['current_timestamps']) {
            $tableChange .= '->nullable()->useCurrent()';
        }
        if ($change['options']['comment'] !== $comment) {
            $tableChange .= '->comment("' . $change['options']['comment'] . '")';
        }

        return $tableChange;
    }
}
