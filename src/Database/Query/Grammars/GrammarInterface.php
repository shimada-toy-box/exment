<?php

namespace Exceedone\Exment\Database\Query\Grammars;

interface GrammarInterface
{
    /**
     * Whether support wherein multiple column.
     *
     * @return bool
     */
    public function isSupportWhereInMultiple() : bool;

    /**
     * wherein string.
     * Ex. column is 1,12,23,31 , and want to match 1, getting.
     *
     * @param \Illuminate\Database\Query\Builder $builder
     * @param string $tableName database table name
     * @param string $column target table name
     * @param array $values
     * @return \Illuminate\Database\Query\Builder
     */
    public function whereInArrayString($builder, string $tableName, string $column, $values) : \Illuminate\Database\Query\Builder;
        
    public function wrapWhereInMultiple(array $columns);

    /**
     * Bind and flatten value results.
     *
     * @return array offset 0: bind string for wherein (?, ?, )
     */
    public function bindValueWhereInMultiple(array $values);

    /**
     * Get cast column string
     *
     * @return string
     */
    public function getCastColumn($type, $column, $options = []);

    /**
     * Get column type string. Almost use virtual column.
     *
     * @return string
     */
    public function getColumnTypeString($type);

    /**
     * Get cast string
     *
     * @return string
     */
    public function getCastString($type, $addOption = false, $options = []);

    /**
     * Get date format string
     *
     * @param GroupCondition $groupCondition Y, YM, YMD, ...
     * @param string $column column name
     * @param bool $groupBy if group by query, return true
     *
     * @return void
     */
    public function getDateFormatString($groupCondition, $column, $groupBy = false, $wrap = true);
    
    /**
     * convert carbon date to date format
     *
     * @param GroupCondition $groupCondition Y, YM, YMD, ...
     * @param \Carbon\Carbon $carbon
     *
     * @return string
     */
    public function convertCarbonDateFormat($groupCondition, $carbon);

    /**
     * Wrap and add json_unquote if needs
     *
     * @param mixed $value
     * @param boolean $prefixAlias
     * @return string
     */
    public function wrapJsonUnquote($value, $prefixAlias = false);
}
