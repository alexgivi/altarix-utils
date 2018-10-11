<?php

namespace altarix\utils;

interface QuerySourceInterface
{
    public function all();

    public function one();

    /**
     * @param string $field
     * @param string $operator
     * @param $value
     * @return $this
     */
    public function where(string $field, string $operator, $value);

    /**
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit);

    /**
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset);

    /**
     * @param array $orders
     * @return $this
     */
    public function order(array $orders);

    public function count(): int;
}
