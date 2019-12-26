<?php

namespace Emonkak\Database;

/**
 * PDOStatementInterface adapter for mysqli_stmt.
 */
class MysqliStmtAdapter implements \IteratorAggregate, PDOStatementInterface
{
    /**
     * @var \mysqli_stmt
     */
    private $stmt;

    /**
     * @var \mysqli_result
     */
    private $result;

    /**
     * @var int
     */
    private $fetch_style = MYSQLI_BOTH;

    /**
     * @var mixed
     */
    private $fetch_argument;

    /**
     * @var mixed
     */
    private $ctor_args;

    /**
     * @var string
     */
    private $bind_types = '';

    /**
     * @var mixed[]
     */
    private $bind_values = [];

    /**
     * @param \mysqli_stmt $stmt
     */
    public function __construct(\mysqli_stmt $stmt)
    {
        $this->stmt = $stmt;
    }

    public function __destruct()
    {
        if ($this->result !== null) {
            $this->result->free();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new PDOStatementIterator($this);
    }

    /**
     * {@inheritDoc}
     */
    public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR)
    {
        switch ($data_type) {
            case \PDO::PARAM_BOOL:
                $this->bind_types .= 'i';
                $this->bind_values[] = $value ? 1 : 0;
                break;

            case \PDO::PARAM_NULL:
                $this->bind_types .= 'i';
                $this->bind_values[] = null;
                break;

            case \PDO::PARAM_INT:
                $this->bind_types .= 'i';
                $this->bind_values[] = $value;
                break;

            // case \PDO::PARAM_STR:
            // case \PDO::PARAM_LOB:
            default:
                $this->bind_types .= is_double($value) ? 'd' : 's';
                $this->bind_values[] = $value;
                break;
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode()
    {
        return $this->stmt->sqlstate;
    }

    /**
     * {@inheritDoc}
     */
    public function errorInfo()
    {
        return [
            $this->stmt->sqlstate,
            $this->stmt->errno,
            $this->stmt->error,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function execute($input_parameters = null)
    {
        $bind_types = $this->bind_types;
        $params = [&$bind_types];

        if ($input_parameters !== null) {
            foreach ($input_parameters as $i => $_) {
                $bind_types .= 's';
                $params[] = &$input_parameters[$i];
            }
        }

        if ($bind_types !== '') {
            foreach ($this->bind_values as $i => $_) {
                $params[] = &$this->bind_values[$i];
            }
            call_user_func_array([$this->stmt, 'bind_param'], $params);
        }

        if (!$this->stmt->execute()) {
            return false;
        }

        $this->result = $this->stmt->get_result();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function fetch($fetch_style = null, $cursor_orientation = null, $cursor_offset = null)
    {
        if (!$this->result) {
            return false;
        }

        if ($fetch_style === null) {
            $fetch_style = $this->fetch_style;
        }

        switch ($fetch_style) {
            case \PDO::FETCH_BOTH:
                return $this->result->fetch_array(MYSQLI_BOTH) ?: false;

            case \PDO::FETCH_ASSOC:
                return $this->result->fetch_array(MYSQLI_ASSOC) ?: false;

            case \PDO::FETCH_NUM:
                return $this->result->fetch_array(MYSQLI_NUM) ?: false;

            case \PDO::FETCH_CLASS:
                $class = $cursor_orientation ?: $this->fetch_argument ?: stdClass::class;
                $params = $cursor_offset ?: $this->ctor_args;
                if ($params !== null) {
                    return $this->result->fetch_object($class, $params) ?: false;
                } else {
                    return $this->result->fetch_object($class) ?: false;
                }

            case \PDO::FETCH_COLUMN:
                $column_number = $cursor_orientation ?: $this->fetch_argument ?: 0;
                return $this->doFetchColumn($column_number);
        }

        throw new \UnexpectedValueException("Unsupported fetch style, got '$fetch_style'");
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = null)
    {
        if (!$this->result) {
            return [];
        }

        if ($fetch_style === null) {
            $fetch_style = $this->fetch_style;
        }

        switch ($fetch_style) {
            case \PDO::FETCH_BOTH:
                return $this->result->fetch_all(MYSQLI_BOTH);

            case \PDO::FETCH_ASSOC:
                return $this->result->fetch_all(MYSQLI_ASSOC);

            case \PDO::FETCH_NUM:
                return $this->result->fetch_all(MYSQLI_NUM);

            case \PDO::FETCH_CLASS:
                $class = $fetch_argument ?: $this->fetch_argument ?: stdClass::class;
                $params = $ctor_args ?: $this->ctor_args;
                $rows = [];
                if ($params !== null) {
                    while (($row = $this->result->fetch_object($class, $params)) !== null) {
                        $rows[] = $row;
                    }
                } else {
                    while (($row = $this->result->fetch_object($class)) !== null) {
                        $rows[] = $row;
                    }
                }
                return $rows;

            case \PDO::FETCH_COLUMN:
                $columns = [];
                $column_number = $fetch_argument ?: $this->fetch_argument ?: 0;
                while (($row = $this->result->fetch_array(MYSQLI_NUM)) !== null) {
                    if (!isset($row[$column_number])) {
                        throw new \RuntimeException('Invalid column index');
                    }
                    $columns[] = $row[$column_number];
                }
                return $columns;
        }

        throw new \UnexpectedValueException("Unsupported fetch style, got '$fetch_style'");
    }

    /**
     * {@inheritDoc}
     */
    public function fetchColumn($column_number = 0)
    {
        if (!$this->result) {
            return false;
        }
        return $this->doFetchColumn($column_number);
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount()
    {
        return $this->stmt->affected_rows;
    }

    /**
     * {@inheritDoc}
     */
    public function setFetchMode($mode, $param1 = null, $param2 = null)
    {
        $this->fetch_style = $mode;
        $this->fetch_argument = $param1;
        $this->ctor_args = $param2;
        return true;
    }

    /**
     * @param int $column_number
     * @return mixed
     */
    private function doFetchColumn($column_number)
    {
        $row = $this->result->fetch_array(MYSQLI_NUM);
        if ($row === null) {
            return false;
        }
        if (!isset($row[$column_number])) {
            return false;
        }
        return $row[$column_number];
    }
}
