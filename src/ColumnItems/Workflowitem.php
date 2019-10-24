<?php

namespace Exceedone\Exment\ColumnItems;

use Encore\Admin\Form\Field\Select;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Model\WorkflowStatus;
use Exceedone\Exment\Model\Define;

class WorkflowItem extends SystemItem
{
    protected $table_name = 'workflow_values';

    protected static $addStatusSubQuery = false;

    protected static $addWorkUsersSubQuery = false;

    /**
     * whether column is enabled index.
     *
     */
    public function sortable()
    {
        return false;
    }

    /**
     * get sql query column name
     */
    protected function getSqlColumnName()
    {
        // get SystemColumn enum
        $option = SystemColumn::getOption(['name' => $this->column_name]);
        if (!isset($option)) {
            $sqlname = $this->column_name;
        } else {
            $sqlname = array_get($option, 'sqlname');
        }
        return $this->table_name.'.'.$sqlname;
    }

    public static function getItem(...$args)
    {
        list($custom_table, $column_name, $custom_value) = $args + [null, null, null];
        return new self($custom_table, $column_name, $custom_value);
    }

    protected function getTargetValue($html)
    {
        $val = parent::getTargetValue($html);

        if (boolval(array_get($this->options, 'summary'))) {
            if (isset($val)) {
                $model = WorkflowStatus::find($val);
                return array_get($model, 'status_name');
            } else {
                return $this->custom_table->workflow->start_status_name;
            }
        }

        return $val;
    }
    
    public function getFilterField($value_type = null)
    {
        $field = new Select($this->name(), [$this->label()]);

        // get workflow statuses
        $workflow = Workflow::getWorkflowByTable($this->custom_table);
        $options = $workflow->getStatusOptions() ?? [];

        $field->options($options);
        $field->default($this->value);

        return $field;
    }

    /**
     * get
     */
    public function getTableName()
    {
        return $this->table_name;
    }

    /**
     * create subquery for join
     */
    public static function getStatusSubquery($query, $custom_table)
    {
        if (static::$addStatusSubQuery) {
            return;
        }
        static::$addStatusSubQuery = true;

        $tableName = getDBTableName($custom_table);
        $subquery = \DB::table($tableName)
            ->leftJoin(SystemTableName::WORKFLOW_VALUE, function ($join) use ($tableName, $custom_table) {
                $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_id', "$tableName.id")
                    ->where(SystemTableName::WORKFLOW_VALUE . '.morph_type', $custom_table->table_name)
                    ->where(SystemTableName::WORKFLOW_VALUE . '.latest_flg', true);
            })->select(["$tableName.id as morph_id", 'morph_type', 'workflow_status_id']);
            
        $query->joinSub($subquery, 'workflow_values', function ($join) use ($tableName) {
            $join->on($tableName . '.id', 'workflow_values.morph_id');
        });
    }

    /**
     * create subquery for join
     */
    public static function getWorkUsersSubQuery($query, $custom_table)
    {
        if (static::$addWorkUsersSubQuery) {
            return;
        }
        static::$addWorkUsersSubQuery = true;

        // get all status and action list in selected custom table
        $statusActions = Workflow::join(SystemTableName::WORKFLOW_ACTION, function ($join) use ($custom_table) {
            $join->on(SystemTableName::WORKFLOW_ACTION . '.workflow_id', SystemTableName::WORKFLOW . '.id');
        })->join(SystemTableName::WORKFLOW_TABLE, function ($join) use ($custom_table) {
            $join->on(SystemTableName::WORKFLOW_TABLE . '.workflow_id', SystemTableName::WORKFLOW . '.id');
        })->where(SystemTableName::WORKFLOW_TABLE . '.custom_table_id', $custom_table->id)
        ->where(SystemTableName::WORKFLOW_ACTION . '.options->ignore_work', '<>', 1)
        ->select(['status_from', SystemTableName::WORKFLOW_ACTION . '.id'])
        ->get()->groupBy('status_from');

        $tableName = getDBTableName($custom_table);

        // get sub query
        $subquery = \DB::table($tableName)
            ->join(SystemTableName::WORKFLOW_VALUE, function ($join) use ($tableName, $custom_table, $statusActions) {
                $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_id', "$tableName.id")
                    ->where(SystemTableName::WORKFLOW_VALUE . '.morph_type', $custom_table->table_name)
                    ->where(SystemTableName::WORKFLOW_VALUE . '.latest_flg', true);
            })
            ->join(SystemTableName::WORKFLOW_AUTHORITY, function ($join) {
            })

            // add where function for action and status pairs
            ->where(function ($query) use ($statusActions) {
                foreach ($statusActions as $key => $statusAction) {
                    $query->orWhere(function ($query) use ($key, $statusAction) {
                        $query->where(SystemTableName::WORKFLOW_VALUE . '.workflow_status_id', $key)
                            // create wherein workflow action id
                            ->whereIn(SystemTableName::WORKFLOW_AUTHORITY . '.workflow_action_id', $statusAction->map(function ($s) {
                                return $s->id;
                            })->toArray());
                    });
                }
            })

            ///// add authority function for user or org
            ->where(function ($query) use ($tableName, $custom_table) {
                $classes = [
                    \Exceedone\Exment\ConditionItems\UserItem::class,
                    \Exceedone\Exment\ConditionItems\OrganizationItem::class,
                    \Exceedone\Exment\ConditionItems\ColumnItem::class,
                    \Exceedone\Exment\ConditionItems\SystemItem::class,
                ];

                foreach ($classes as $class) {
                    $class::setConditionQuery($query, $tableName, $custom_table);
                }
            })

            ->select(["$tableName.id as morph_id", 'morph_type']);
            
        $query->joinSub($subquery, 'workflow_values_wf', function ($join) use ($tableName) {
            $join->on($tableName . '.id', 'workflow_values_wf.morph_id');
        });

        //$query = \DB::query()->fromSub($query, 'sub');
    }

    /**
     * set workflow status or work user condition
     */
    public static function scopeWorkflow($query, $view_column_target_id, $custom_table, $condition, $status)
    {
        $enum = SystemColumn::getEnum($view_column_target_id);
        if ($enum == SystemColumn::WORKFLOW_WORK_USERS) {
            //static::scopeWorkflowWorkUsers($query, $custom_table, $condition, $status);
        } else {
            static::scopeWorkflowStatus($query, $custom_table, $condition, $status);
        }
    }

    /**
     * set workflow status condition
     */
    public static function scopeWorkflowStatus($query, $custom_table, $condition, $status)
    {
        ///// Introduction: When the workflow status is "start", one of the following two conditions is required.
        ///// *No value in workflow_values ​​when registering data for the first time
        ///// *When workflow_status_id of workflow_values ​​is null. Ex.Rejection

        // if $status is start
        if ($status == Define::WORKFLOW_START_KEYNAME) {
            $func = ($condition == FilterOption::NE) ? 'whereNotNull' : 'whereNull';
            $query->{$func}('workflow_status_id');
        } else {
            $mark = ($condition == FilterOption::NE) ? '<>' : '=';
            $query->where('workflow_status_id', $mark, $status);
        }

        return $query;
    }
    
    /**
     * set workflow work users condition
     */
    protected static function scopeWorkflowWorkUsers($query, $custom_table, $condition, $value)
    {
        // if $status is start
        // if($value == Define::WORKFLOW_START_KEYNAME){
        //     $func = ($condition == FilterOption::NE) ? 'whereNotNull' : 'whereNull';
        //     $query->{$func}('workflow_status_id');
        // }else{
        //     $mark = ($condition == FilterOption::NE) ? '<>' : '=';
        //     $query->where('workflow_status_id', $mark, $status);
        // }

        return $query;
    }
}
