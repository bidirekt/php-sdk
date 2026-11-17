<?php

abstract class Model
{
    /**
     * @bidirekt field
     * @var integer
     */
    public $id;

    /**
     * @param array $row
     * @return static
     */
    public static function fromRow($row)
    {
        $model = new static();
        foreach ($row as $column => $value) {
            if (property_exists($model, $column)) {
                $model->$column = $value;
            }
        }
        return $model;
    }
}
