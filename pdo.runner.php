<?php
class PDO_Runner extends PDO {

    private $cache_query = array();
    private $directives = array('>','<','>=','<=','<>','!=','IS','NOT');

    function __construct() 
    {

        parent::__construct('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASSWORD);
        try 
        {
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
        } 
        catch(PDOException $ex) 
        {
           die('Error connecting to database.');
        }

        $this->init();

        return $this;
    }

    function init()
    {
        $this->field = $this->condition = $this->or = $this->param = $this->set = $this->join = $this->order_by = $this->group_by = array();
        $this->primary_col = $this->command = $this->table = $this->sql = $this->limit = NULL;
        $this->data_grid = FALSE;
    }

    function get($table, $field = NULL) 
    {
        $this->primary_col = $this->get_primary_key($table);
        $this->table = DB_prefix . $table;

        $this->field[] = $this->table . '.' . $this->primary_col;

        if(is_array($field))
        {
            foreach($field as $v)
            {
                if($v != $this->primary_col)
                    $this->field[] = $this->table . '.' . $v;
            }
        }
        else
        {
            $this->field = array_merge($this->field, $this->get_cols_from($this->table));
        }

        $this->command = 'SELECT';
        $this->data_grid = TRUE;

        return $this;
    }

    function get_row($table, $field = NULL) 
    {
        $this->get($table, $field);
        $this->data_grid = FALSE;

        return $this;
    }

    function insert($table, $field = NULL)
    {
        //$this->db->insert('table1',[field1,field2,field3])->value([1,2,3])->value([1,2,3])->run();
        $this->table = DB_prefix . $table;
        $this->command = 'INSERT';

        if(is_array($field))
            $this->field = '(' . implode(',', $field) . ')';

        return $this;
    }

    function update($table)
    {
        $this->primary_col = $this->get_primary_key($table);

        $this->table = DB_prefix . $table;
        $this->command = 'UPDATE';

        return $this;
    }

    function delete($table)
    {
        $this->primary_col = $this->get_primary_key($table);

        $this->table = DB_prefix . $table;
        $this->command = 'DELETE';

        return $this;
    }

    function search($term, $field = NULL)
    {
        if($field)
            $col = $field;
        else
            $col = $this->get_cols_from($this->table);

        $match = array();

        if(is_array($term))
        {
            foreach($term as $k => $v)
            {
                $col_count = 1;
                foreach($col as $v2)
                {
                    $parameterize = ':search_' . $v2 . '_' . $col_count;

                    $this->whr_or($v2, 'LIKE', '%' . $v . '%');
                    $match[] = 'WHEN ' . $this->table . '.' .$v2 . ' LIKE ' . $parameterize . ' THEN ' . $col_count;
                    $this->param[$parameterize] = '%' . $v . '%';

                    $col_count++;
                }
            }
        }
        else
        {
            $col_count = 1;
            foreach($col as $v)
            {
                $parameterize = ':search_' . $v . '_' . $col_count;

                $this->whr_or($v, 'LIKE', '%' . $term . '%');
                $match[] = 'WHEN ' . $this->table . '.' .$v . ' LIKE ' . $parameterize . ' THEN ' . $col_count;
                $this->param[$parameterize] = '%' . $v . '%';

                $col_count++;
            }
        }

        $this->order_by[] = 'CASE ' . implode(' ', $match) . ' ELSE ' . ($col_count+1) . ' END asc';

        return $this;
    }

    function values($values)
    {
        $param = array();
        foreach($values as $k => $v)
        {
            $param[] = '?';
            $this->param[] = $v;
        }

        $this->value[] = implode(',', $param);
       
        return $this;
    }

    function set($col,$value)
    {
        $table_col = $this->table.'.'.$col;
        $parameterize = ':upd_' . $this->table . '_'.$col;

        $this->set[] = $table_col . ' = ' . $parameterize;
        $this->param[$parameterize] = strlen($value) > 0 ? $value : 'NULL';

        return $this;
    }

    function where($col, $directive, $value)
    {
        if($col && $directive && strlen($value))
        {
            $table_col = $this->table.'.'.$col;
            $parameterize = ':whr_' . $this->table.'_'.$col;

            $this->condition[] = $table_col . ' ' . $directive . ' ' . (strlen($value) == 0 ? ' NULL' : $parameterize);

            if(strlen($value) > 0)
                $this->param[$parameterize] = $value;
        }

        return $this;
    }

    function row($row_id)
    {
        $table_col = $this->table.'.'.$this->primary_col;
        $parameterize = ':row_' . $this->table.'_'.$this->primary_col;

        $this->condition[] = $table_col . ' = ' . $parameterize;
        $this->param[$parameterize] = $row_id;

        return $this;
    }

    function in($col, $value)
    {
        $table_col = $this->table.'.'.$col;
        $parameterize = ':in_' . $this->table.'_'.$col;
        $in = array();

        if(is_array($value))
        {
            foreach ($value as $k => $v) {
                 $in[] = $parameterize . '_' .$k;
                 $this->param[$parameterize . '_' .$k] = $v;
            }
        }

        $this->condition[] = $table_col . ' IN (' .implode(',', $in) . ')';

        return $this;
    }

    function not_in($col, $value)
    {
        $table_col = $this->table.'.'.$col;
        $parameterize = ':not_in_' . $this->table.'_'.$col;
        $not = array();

        if(is_array($value))
        {
            foreach ($value as $k => $v) {
                 $not[] = $parameterize . '_' .$k;
                 $this->param[$parameterize . '_' .$k] = $v;
            }
        }
        
        $this->condition[] = $table_col . ' NOT IN (' .implode(',', $not) . ')';

        return $this;
    }

    function whr_or($col, $directive, $value)
    {
        if($col && $directive && strlen($value))
        {
            $table_col = $this->table.'.'.$col;
            $parameterize = ':whr_or_' . $this->table.'_'.$col;

            $this->or[] = $table_col . ' ' . $directive . ' ' . (strlen($value) == 0 ? ' NULL' : $parameterize);

            if(strlen($value) > 0)
                $this->param[$parameterize] = $value;
        }

        return $this;
    }

    function between($col, $value1, $value2)
    {
        $col = $this->table.'.'.$col;

        $param_1 = ':' . $col . '_0';
        $param_2 = ':' . $col . '_1';

        $this->param[$param_1] = $value1;
        $this->param[$param_2] = $value2;

        $this->condition[] = $col . ' BETWEEN ' . $param_1 . ' AND ' . $param_2;

        return $this;
    }

    function not_between($col, $value1, $value2)
    {
        $col = $this->table.'.'.$col;
        $param_1 = ':' . $col . '_0';
        $param_2 = ':' . $col . '_1';

        $this->param[$param_1] = $value1;
        $this->param[$param_2] = $value2;

        $this->condition[] = $col . ' NOT BETWEEN ' . $param_1 . ' AND ' . $param_2;

        return $this;
    }

    function left_join($table, $table1_col, $table2_col, $field = NULL)
    {
        $table = DB_prefix.$table;
        $table1_col = $this->table.'.'.$col_name1;
        $table2_col = DB_prefix.$table.'.'.$col_name2;

        if(is_array($field))
            $this->field = array_merge($this->field, $this->get_cols_from($table));
        else
            $this->field = $field;

        $this->join[] = ' LEFT JOIN ' . $table . ' ON ' . $table1_col . ' = ' . $table2_col;

        return $this;
    }

    function right_join($table, $table1_col, $table2_col, $field = NULL)
    {
        $table = DB_prefix.$table;
        $table1_col = $this->table.'.'.$col_name1;
        $table2_col = DB_prefix.$table.'.'.$col_name2;

        if(is_array($field))
            $this->field = array_merge($this->field, $this->get_cols_from($table));
        else
            $this->field = $field;

        $this->join[] = ' RIGHT JOIN ' . $table . ' ON ' . $table1_col . ' = ' . $table2_col;

        return $this;
    }

    function limit($offset, $num_row = null)
    {
        if($num_row)
            $this->limit = ' LIMIT '.$offset.','.$num_row;
        else
            $this->limit = ' LIMIT '.$offset;

        return $this;
    }

    function order_by($col, $order)
    {
        $col = $this->table.'.'.$col;

        $this->order_by[] = $col . ' ' . $order;

        return $this;
    }

    function random()
    {
        $this->order_by = ' RANDOM()';

        return $this;
    }

    function group_by($col)
    {
        $col = $this->table.'.'.$col;

        $this->group_by[] = $col . ' ' . $order;

        return $this;
    }

    function run()
    {
        try
        {
            $this->sql = $this->build_query();

            /*echo $this->sql;
            echo '<br />';
            print_r($this->param);*/

            $stmt = $this->prepare($this->sql);

            if($this->param)
                $stmt->execute($this->param);
            else
                $stmt->execute();

            if($this->data_grid)
            {
                $this->total_rows = $this->get_total_rows();
                $this->init();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            else
            {
                $this->init();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        catch(PDOException $e)
        {
            $this->sql_error($e->getMessage());
        }
    }

    function build_query()
    {
        /*$command = $this->command;
        $join = $this->join;
        $condition = $this->condition;
        $group_by = $this->group_by;
        $order_by = $this->order_by;
        $limit = $this->limit;*/

        $select_query = '[COMMAND] [FIELD] FROM [TABLE] [JOIN] [CONDITION] [GROUP] [ORDER] [LIMIT]';
        $insert_query = '[COMMAND] INTO [TABLE] [FIELD] VALUES ([VALUES])';
        $update_query = '[COMMAND] [TABLE] [SETS] [CONDITION]';
        $delete_query = '[COMMAND] FROM [TABLE] [CONDITION]';

        switch($this->command)
        {
            case 'SELECT':
                $command = $this->command;
                $field = implode(',', $this->field);
                $table = $this->table;
                $join = implode(' ', $this->join);
                $or = $this->or ? implode(' OR ', $this->or) : NULL;

                if($or)
                    $this->condition[] = $or;

                if(is_array($this->order_by))
                    rsort($this->order_by);

                $condition = $this->condition ? 'WHERE '.implode(' AND ', $this->condition) : NULL;
                $group_by = $this->group_by ? 'GROUP BY '.implode(' AND ', $this->group_by) : NULL;
                $order_by = $this->order_by && is_array($this->order_by) ? ('ORDER BY '.implode(',', $this->order_by)) : (is_string($this->order_by) ? 'ORDER BY '.$this->order_by : NULL);
                $limit = $this->limit ? $this->limit : NULL;

                $sql = $select_query;
                $sql = str_replace('[COMMAND]', ($command ? $command : ''), $sql);
                $sql = str_replace('[FIELD]', ($field ? $field : ''), $sql);
                $sql = str_replace('[TABLE]', ($table ? $table : ''), $sql);
                $sql = str_replace('[JOIN]', ($join ? $join : ''), $sql);
                $sql = str_replace('[CONDITION]', ($condition ? $condition : ''), $sql);
                $sql = str_replace('[GROUP]', ($group_by ? $group_by : ''), $sql);
                $sql = str_replace('[ORDER]', ($order_by ? $order_by : ''), $sql);
                $sql = str_replace('[LIMIT]', ($limit ? $limit : ''), $sql);

                $this->cache_query[] = array(
                    'command' => $this->command,
                    'field' => $this->field,
                    'table' => $this->table,
                    'join' => $this->join,
                    'condition' => $this->condition,
                    'group' => $this->group_by,
                    'order' => $this->order_by,
                    'limit' => $this->limit
                );
            break;

            case 'INSERT':
                $command = $this->command;
                $table = $this->table;
                $field = $this->field;
                $value = implode('),(', $this->value);

                $sql = $insert_query;
                $sql = str_replace('[COMMAND]', ($command ? $command : ''), $sql);
                $sql = str_replace('[TABLE]', ($table ? $table : ''), $sql);
                $sql = str_replace('[FIELD]', ($field ? $field : ''), $sql);
                $sql = str_replace('[VALUES]', ($value ? $value : ''), $sql);

                $this->cache_query[] = array(
                    'command' => $this->command,
                    'field' => $this->field,
                    'table' => $this->table,
                    'value' => $this->value
                );
            break;

            case 'UPDATE':
                $command = $this->command;
                $table = $this->table;
                $set = $this->set ? 'SET ' . implode(' , ', $this->set) : NULL;
                $condition = $this->condition ? 'WHERE '.implode(' AND ', $this->condition) : NULL;

                $sql = $update_query;
                $sql = str_replace('[COMMAND]', ($command ? $command : ''), $sql);
                $sql = str_replace('[TABLE]', ($table ? $table : ''), $sql);
                $sql = str_replace('[SETS]', ($set ? $set : ''), $sql);
                $sql = str_replace('[CONDITION]', ($condition ? $condition : ''), $sql);

                $this->cache_query[] = array(
                    'command' => $this->command,
                    'table' => $this->table,
                    'set' => $this->set,
                    'condition' => $this->condition
                );
            break;

            case 'DELETE':
                $command = $this->command;
                $table = $this->table;
                $condition = $this->condition ? 'WHERE '.implode(' AND ', $this->condition) : NULL;

                $sql = $delete_query;
                $sql = str_replace('[COMMAND]', ($command ? $command : ''), $sql);
                $sql = str_replace('[TABLE]', ($table ? $table : ''), $sql);
                $sql = str_replace('[CONDITION]', ($condition ? $condition : ''), $sql);

                $this->cache_query[] = array(
                    'command' => $this->command,
                    'table' => $this->table,
                    'condition' => $this->condition
                );
            break;
        }

        $sql = preg_replace('/\s+/', ' ', $sql);

        return $sql;
    }

    function count($col)
    {
        //'SELECT table_rows FROM `information_schema`.`tables` WHERE table_name = `_master_table_enquiry`.`enquiry_table` LIMIT 1'

        $this->field[] = 'COUNT(' . $this->table . '.' . $col . ') AS total_' . $col;
    }

    function count_row_from_col($table)
    {
        $this->left_join($table, $table);
        $this->field[] = $table . '.total_rows';
    }

    function get_total_rows()
    {
        $sql = str_replace($this->limit, '', $this->sql);
        $sql = 'SELECT COUNT(count_table.'.$this->primary_col.') as total FROM ('.$sql.') as count_table';

        $stmt = $this->prepare($sql);
        if(isset($this->param))
            $stmt->execute($this->param);
        else
            $stmt->execute();

        $count_row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $count_row['total'];
    }

    function get_primary_key($table)
    {
        $stmt = $this->prepare('DESCRIBE '.$table);
        $stmt->execute();

        while($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if($row['Key'] == 'PRI' || $row['Extra'] == 'auto_increment') 
                return $row['Field'];
        }
        return FALSE;
    }

    function sql_error($msg = NULL)
    {
        echo $this->sql;
        echo '<br />';
        print_r($this->param);

        if($msg)
            die('Error in query: '. $msg);
        else
            die('Error in query');
    }

    function get_cols_from($table, $col = NULL)
    {
        $stmt = $this->prepare('DESCRIBE '.$table);
        $stmt->execute();

        $rows = array();
        while($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if($row['Extra'] != 'auto_increment' && $row['Field'] != $this->primary_col) 
                $rows[] = $table . '.' . $row['Field'];
            elseif($row['Field'] == $col)
                return $col;
        }

        return $rows;
    }

    function drop_table($table)
    {

        if($this->get_primary_key($table))
        {
            $sql = 'DROP TABLE ' . $table;

            return $this->exec($sql);
        }
        else
        {
            return FALSE;
        }
    }
}