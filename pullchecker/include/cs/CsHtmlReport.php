<?php

/**
 * Works with CsWrapper.php
 * Purpose:  generate HTML report using result array which returned by CsWrapper
 * Features: report includes errors with line/column locations and the highlighted code
 *
 * Written by Dmitri Pluschaev d.pluschaev@intetics.com
 */

class CsHtmlReport
{
    protected $data;
    protected $columns;
    protected $entities;
    protected $report_key;
    protected $is_full_report;

    protected $stat_lines_with_errors;
    protected $stat_errors_total;
    protected $stat_lines_changed;

    protected $callbacks;

    public function __construct(array $data, $is_full_report, array $callbacks = array())
    {
        $this->data = $data;
        $this->columns = array(
            'number' => '#',
            'line' => 'Line',
            'col' => 'Col',
            'description' => 'Description',
            'code' => 'Code String',
        );
        $this->entities = array(
            'table' => '<table class="%s" cellpadding="0" cellspacing="0">%s</table>',
            'thead' => '<thead class="%s">%s</thead>',
            'tbody' => '<tbody class="%s">%s</tbody>',
            'th' => '<th class="%s">%s</th>',
            'tr' => '<tr class="%s">%s</tr>',
            'td' => '<td class="%s">%s</td>',
            'td_grouped' => '<td colspan="4" class="%s">%s</td>',
            'td_title' => '<td colspan="' . sizeof($this->columns) . '" class="%s">%s</td>',
            'div' => '<div class="%s">%s</div>',
            'span' => '<span class="%s">%s</span>',
            'code_bof' => '<div class="code_bof">&gt;&gt; BEGIN OF THE FILE</div>',
            'code_eof' => '<div class="code_eof">&gt;&gt; EOF</div>',
            'sym_tab' => '<span class="sym_tab" title="Here is a Tab symbol">TAB</span>',
            'highlight' => '<span class="highlight" title="%s">%s</span>',
        );
        $this->report_key = $is_full_report ? 'messages' : 'report_for_lines';
        $this->is_full_report = $is_full_report;

        $this->callbacks = $callbacks;
    }

    protected function entity($name, $class = '', $value = '', $new_line_disabled = false)
    {
        return ($new_line_disabled ? '' : "\n") . sprintf($this->entities[$name], $class ? $class : 'none', $value);
    }

    protected function createTrs()
    {
        $trs = '';
        foreach ($this->data as $item) {
            if (!empty($item[$this->report_key])) {
                $trs .= $this->tdTitle(array(), $item);
                $issue_number = 0;
                foreach ($item[$this->report_key] as $line => $columns) {
                    $this->stat_lines_with_errors++;

                    // group numbers, line/cols, descriptions
                    $prepared = array('number' => '', 'line' => '', 'col' => '', 'description' => '');
                    $group_continue = false;
                    $nested_table = '';
                    foreach ($columns as $col => $errors) {
                        foreach ($errors as $error) {
                            $this->stat_errors_total++;
                            $issue_number++;
                            $error['number'] = $issue_number;
                            $error['line'] = $group_continue ? '' : $line;
                            $error['col'] = $col;
                            $cols = $this->getColumnsArray($error, $item, $prepared);
                            foreach ($cols as $k => $v) {
                                $prepared[$k] = $this->entity('td', 'td_' . $k, $v);
                            }
                            $nested_table .= $this->entity('tr', '', implode('', $prepared));
                            $group_continue = true;
                        }
                    }

                    $diff = $this->getColumnsArray(
                        array('line' => $line, 'columns' => $columns),
                        $item,
                        array_diff_key($this->columns, $prepared)
                    );

                    $nested_table = $this->entity('tbody', '', $nested_table);
                    $nested_table = $this->entity('table', 'table_grouped', $nested_table);

                    $tds = $this->entity('td_grouped', 'td_grouped', $nested_table);

                    foreach ($diff as $k => $v) {
                        $tds .= $this->entity('td', 'td_' . $k, $v);
                    }

                    $trs .= $this->entity(
                        'tr',
                        $this->stat_lines_with_errors % 2 ? 'even' : 'odd',
                        $tds
                    );
                }
            }
        }
        return $trs;
    }

    protected function createThs()
    {
        $ths = '';
        foreach ($this->columns as $key => $title) {
            $ths .= $this->entity('th', 'th_' . $key, $title);
        }
        return $ths;
    }

    protected function createTable()
    {
        // create thead
        $thead = $this->entity('thead', '', $this->createThs());

        // create tbody
        $tbody = $this->entity('tbody', '', $this->createTrs());

        // create table
        return $this->entity(
            'table',
            'report',
            $thead . $tbody
        );
    }

    public function generate()
    {
        $this->stat_lines_with_errors = $this->stat_lines_changed = $this->stat_errors_total = 0;

        foreach ($this->data as $file) {
            $this->stat_lines_changed += sizeof($file[$this->is_full_report ? 'code_lines' : 'lines']);
        }
        return $this->createTable();
    }

    public function generateFullPageHTML($title = 'Code Style Report')
    {
        $dir = dirname(__FILE__);
        $report = $this->generate();
        $style = file_get_contents($dir . '/CsHtmlReport.css');
        $stats = $this->getStats();
        ob_start();
        require $dir . '/CsHtmlReport.tpl';
        return ob_get_clean();
    }

    public function getStats()
    {
        return array(
            'lines_changed' => $this->stat_lines_changed,
            'errors_total' => $this->stat_errors_total,
            'lines_with_errors' => $this->stat_lines_with_errors,
            'crap_rating' => round(($this->stat_lines_with_errors / $this->stat_lines_changed) * 100, 2),
        );
    }

    // Columns
    protected function tdTitle(array $error, array $item)
    {
        $string = $item['id'];
        if (isset($this->callbacks['title']) && is_callable($this->callbacks['title'])) {

            $string = call_user_func_array($this->callbacks['title'], array($string, $error, $item));
        }
        return $this->entity('td_title', 'file', $string);
    }

    protected function tdNumber(array $error, array $item)
    {
        return $error['number'];
    }

    protected function tdLine(array $error, array $item)
    {
        return $error['line'];
    }

    protected function tdCol(array $error, array $item)
    {
        return $error['col'];
    }

    protected function tdDescription(array $error, array $item)
    {
        return $error['type'] . ': ' . $this->toHtml($error['message']);
    }

    protected function tdCode(array $error, array $item)
    {
        $code_around = '';
        for ($i = $error['line'] - 2; $i < $error['line'] + 3; $i++) {
            if (isset($item['code_lines'][$i - 1])) {
                $code_line = $item['code_lines'][$i - 1];

                if ($i == $error['line']) {
                    $code_line = $this->highlightSymbols($code_line, $error['columns']);
                } else {
                    $code_line = $this->toHtml($code_line);
                }

                $ln = $this->entity('td', 'code_ln', $i);
                $lv = $this->entity(
                    'td',
                    $error['line'] == $i
                        ? 'code_line'
                        : 'code_lv',
                    $code_line
                );
                $code_around .= $this->entity('tr', '', $ln . $lv);
            } elseif ($i < $error['line']) {
                if (isset($item['lines'][$i])) {
                    $ln = $this->entity('td', 'code_ln', '&nbsp;');
                    $lv = $this->entity('td', '', $this->entity('code_bof'));
                    $code_around .= $this->entity('tr', '', $ln . $lv);
                }
            } elseif ($i > $error['line']) {
                $ln = $this->entity('td', 'code_ln', '&nbsp;');
                $lv = $this->entity('td', '', $this->entity('code_eof'));
                $code_around .= $this->entity('tr', '', $ln . $lv);
                break;
            }
        }
        return $this->entity('table', 'code', $code_around ? $code_around : '&nbsp;');
    }

    protected function getColumnsArray(array $error, array $item, array $columns = array())
    {
        $cols = array();
        $columns = empty($columns) ? $this->columns : $columns;
        foreach ($columns as $col => $title) {
            $method = 'td' . $col;
            $cols[$col] = $this->$method($error, $item);
        }
        return $cols;
    }

    protected function toHtml($str)
    {
        return strlen($str)
            ? str_replace(array(' ', chr(9)), array('&nbsp;', $this->entity('sym_tab')), htmlspecialchars($str))
            : '&nbsp;';
    }

    protected function highlightSymbols($str, array $positions)
    {
        // highlight symbols
        $cl_parts = array();
        $offset = 0;
        // split by col. numbers
        foreach ($positions as $col => $column_data) {
            $substring = substr($str, $offset, $col - 1 - $offset);
            if (strlen($substring)) {
                $cl_parts[] = array(
                    'is_symbol' => 0,
                    'value' => $this->toHtml($substring),
                );
            }

            // get errors for the symbol
            $errors = array();
            foreach ($column_data as $error) {
                $errors[] = $this->toHtml($error['type'] . ': ' . $error['message']);
            }

            $cl_parts[] = array(
                'is_symbol' => 1,
                'col_val' => implode("\n", $errors),
                'value' => $this->toHtml($str{$col - 1}),
            );
            $offset = $col;
        }
        $cl_parts[] = array(
            'is_symbol' => 0,
            'value' => $this->toHtml(substr($str, $offset)),
        );
        // now join using highlighting
        $str = '';
        foreach ($cl_parts as $part) {
            if ($part['is_symbol']) {
                $str .= $this->entity('highlight', $part['col_val'], $part['value'], 1);
            } else {
                $str .= $part['value'];
            }
        }
        return $str;
    }
}

