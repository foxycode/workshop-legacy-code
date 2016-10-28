<?php

/**
 * Database helpers.
 */
class DbHelpers
{
    use Nette\StaticClass;

    /** @var int maximum SQL length */
    public static $maxLength = 100;

    /**
     * Returns syntax highlighted SQL command.
     * @param  string
     * @return string
     */
    public static function dumpSql($sql, array $params = NULL, DbPDO $connection = NULL)
    {
        static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE';
        static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|REGEXP|TRUE|FALSE';

        // insert new lines
        $sql = " $sql ";
        $sql = preg_replace("#(?<=[\\s,(])($keywords1)(?=[\\s,)])#i", "\n\$1", $sql);

        // reduce spaces
        $sql = preg_replace('#[ \t]{2,}#', ' ', $sql);

        $sql = wordwrap($sql, 100);
        $sql = preg_replace('#([ \t]*\r?\n){2,}#', "\n", $sql);

        // syntax highlight
        $sql = htmlspecialchars($sql, ENT_IGNORE, 'UTF-8');
        $sql = preg_replace_callback("#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is", function ($matches) {
            if (!empty($matches[1])) { // comment
                return '<em style="color:gray">' . $matches[1] . '</em>';

            } elseif (!empty($matches[2])) { // error
                return '<strong style="color:red">' . $matches[2] . '</strong>';

            } elseif (!empty($matches[3])) { // most important keywords
                return '<strong style="color:blue">' . $matches[3] . '</strong>';

            } elseif (!empty($matches[4])) { // other keywords
                return '<strong style="color:green">' . $matches[4] . '</strong>';
            }
        }, $sql);

        // parameters
        $sql = preg_replace_callback('#\?#', function () use ($params, $connection) {
            static $i = 0;
            if (!isset($params[$i])) {
                return '?';
            }
            $param = $params[$i++];
            if (is_string($param) && (preg_match('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]#u', $param) || preg_last_error())) {
                return '<i title="Length ' . strlen($param) . ' bytes">&lt;binary&gt;</i>';

            } elseif (is_string($param)) {
                $length = Nette\Utils\Strings::length($param);
                $truncated = Nette\Utils\Strings::truncate($param, self::$maxLength);
                $text = htmlspecialchars($connection ? $connection->_escape($truncated) : '\'' . $truncated . '\'', ENT_NOQUOTES, 'UTF-8');
                return '<span title="Length ' . $length . ' characters">' . $text . '</span>';

            } elseif (is_resource($param)) {
                $type = get_resource_type($param);
                if ($type === 'stream') {
                    $info = stream_get_meta_data($param);
                }
                return '<i' . (isset($info['uri']) ? ' title="' . htmlspecialchars($info['uri'], ENT_NOQUOTES, 'UTF-8') . '"' : NULL)
                . '>&lt;' . htmlspecialchars($type, ENT_NOQUOTES, 'UTF-8') . ' resource&gt;</i> ';

            } else {
                return htmlspecialchars($param, ENT_NOQUOTES, 'UTF-8');
            }
        }, $sql);

        return '<pre class="dump">' . trim($sql) . "</pre>\n";
    }

    public static function createDebugPanel($connection, $explain = TRUE, $name = NULL)
    {
        $panel = new ConnectionPanel($connection);
        $panel->explain = $explain;
        $panel->name = $name;
        Tracy\Debugger::getBar()->addPanel($panel);
        return $panel;
    }
}
