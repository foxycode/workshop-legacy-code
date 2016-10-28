<?php

/**
 * Debug panel for Nette\Database.
 */
class ConnectionPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    /** @var int */
    public $maxQueries = 100;

    /** @var int logged time */
    private $totalTime = 0;

    /** @var int */
    private $count = 0;

    /** @var array */
    private $queries = [];

    /** @var string */
    public $name;

    /** @var bool|string explain queries? */
    public $explain = TRUE;

    /** @var bool */
    public $disabled = FALSE;


    public function __construct(DbPDOCore $connection)
    {
        $connection->onQuery[] = [$this, 'logQuery'];
    }


    public function logQuery(DbPDOCore $connection, $sql, $result, $time)
    {
        if ($this->disabled) {
            return;
        }
        $this->count++;

        $source = NULL;
        $trace = $result instanceof \PDOException ? $result->getTrace() : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $row) {
            if (isset($row['file']) && is_file($row['file']) && !Tracy\Debugger::getBlueScreen()->isCollapsed($row['file'])) {
                if ((isset($row['function']) && strpos($row['function'], 'call_user_func') === 0)
                    || (isset($row['class']) && is_subclass_of($row['class'], '\\Nette\\Database\\Connection'))
                ) {
                    continue;
                }
                $source = [$row['file'], (int) $row['line']];
                break;
            }
        }
        if ($result instanceof PDOStatement) {
            $this->totalTime += $time;
            if ($this->count < $this->maxQueries) {
//                $this->queries[] = [$connection, $result->getQueryString(), $result->getParameters(), $source, $result->getTime(), $result->getRowCount(), NULL];
                $this->queries[] = [$connection, $sql, NULL, $source, $time, $result->rowCount(), NULL];
            }

        } elseif ($result instanceof \PDOException && $this->count < $this->maxQueries) {
            $this->queries[] = [$connection, $result->queryString, NULL, $source, NULL, NULL, $result->getMessage()];
        }
    }


    public static function renderException($e)
    {
        if (!$e instanceof \PDOException) {
            return;
        }
        if (isset($e->queryString)) {
            $sql = $e->queryString;

        } elseif ($item = Tracy\Helpers::findTrace($e->getTrace(), 'PDO::prepare')) {
            $sql = $item['args'][0];
        }
        return isset($sql) ? [
            'tab' => 'SQL',
            'panel' => DbHelpers::dumpSql($sql),
        ] : NULL;
    }


    public function getTab()
    {
        $name = $this->name;
        $count = $this->count;
        $totalTime = $this->totalTime;
        ob_start(function () {});
        require __DIR__ . '/templates/ConnectionPanel.tab.phtml';
        return ob_get_clean();
    }


    public function getPanel()
    {
        $this->disabled = TRUE;
        if (!$this->count) {
            return;
        }

        $name = $this->name;
        $count = $this->count;
        $totalTime = $this->totalTime;
        $queries = [];
        foreach ($this->queries as $query) {
            /** @var DbPDO $connection */
            list($connection, $sql, $params, $source, $time, $rows, $error) = $query;
            $explain = NULL;
            if (!$error && $this->explain && preg_match('#\s*\(?\s*SELECT\s#iA', $sql)) {
                try {
                    $cmd = is_string($this->explain) ? $this->explain : 'EXPLAIN';
                    $explain = $connection->query("$cmd $sql")->fetchAll();
                } catch (\PDOException $e) {
                }
            }
            $query[] = $explain;
            $queries[] = $query;
        }

        ob_start(function () {});
        require __DIR__ . '/templates/ConnectionPanel.panel.phtml';
        return ob_get_clean();
    }

}
