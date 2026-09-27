<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Reads the first sheet of a CSV or Excel (.xlsx) file into rows of strings.
 * No external library: .xlsx is a ZIP of XML parts (PHP zip extension).
 * Date-formatted Excel cells are returned as Y-m-d. The old binary .xls
 * format is not supported (save it as .xlsx or .csv).
 */
class Ams_spreadsheet_reader
{
    /** @return array ['rows' => [[...], ...]] or ['error' => message] */
    public function read($path, $originalName, $maxRows = 5000)
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') {
            $rows = $this->csv($path, $maxRows);
        } elseif ($ext === 'xlsx') {
            $rows = $this->xlsx($path, $maxRows);
        } else {
            return ['error' => _l('ams_import_bad_type')];
        }
        if (is_string($rows)) {
            return ['error' => $rows];
        }

        // Drop fully empty rows, trim cells.
        $rows = array_values(array_filter(array_map(fn ($r) => array_map(fn ($c) => trim((string) $c), $r), $rows), fn ($r) => implode('', $r) !== ''));
        if (count($rows) < 2) {
            return ['error' => _l('ams_import_empty')];
        }

        return ['rows' => $rows];
    }

    private function csv($path, $maxRows)
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return _l('ams_upload_failed');
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        // Delimiter: whichever of , ; tab appears most in the header line.
        $first = strtok($raw, "\r\n");
        $delim = ',';
        $best  = 0;
        foreach ([',', ';', "\t"] as $d) {
            if (substr_count((string) $first, $d) > $best) {
                $best  = substr_count((string) $first, $d);
                $delim = $d;
            }
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            $rows[] = $r;
            if (count($rows) > $maxRows + 1) {
                fclose($fh);

                return _l('ams_import_too_many', $maxRows);
            }
        }
        fclose($fh);

        return $rows;
    }

    private function xlsx($path, $maxRows)
    {
        if (! class_exists('ZipArchive')) {
            return _l('ams_import_no_zip');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return _l('ams_import_bad_file');
        }

        $sheetPath = $this->first_sheet_path($zip);
        $sheetXml  = $sheetPath ? $zip->getFromName($sheetPath) : false;
        if ($sheetXml === false) {
            $zip->close();

            return _l('ams_import_bad_file');
        }

        $shared    = $this->shared_strings($zip);
        $dateStyle = $this->date_styles($zip);
        $zip->close();

        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        libxml_use_internal_errors($prev);
        if (! $xml) {
            return _l('ams_import_bad_file');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            $max   = -1;
            foreach ($row->c as $c) {
                $col = $this->col_index(preg_replace('/\d+/', '', (string) $c['r']));
                $max = max($max, $col);
                $t   = (string) $c['t'];
                $v   = (string) $c->v;

                if ($t === 's') {
                    $v = $shared[(int) $v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $v = $this->rich_text($c->is);
                } elseif ($t === 'b') {
                    $v = $v === '1' ? '1' : '0';
                } elseif ($t === '' || $t === 'n') {
                    if ($v !== '' && is_numeric($v) && isset($dateStyle[(int) $c['s']])) {
                        $v = $this->excel_date((float) $v);
                    } elseif ($v !== '' && is_numeric($v) && strpos($v, 'E') === false) {
                        $v = rtrim(rtrim(number_format((float) $v, 10, '.', ''), '0'), '.');
                    }
                }
                $cells[$col] = $v;
            }
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
            if (count($rows) > $maxRows + 1) {
                return _l('ams_import_too_many', $maxRows);
            }
        }

        // Pad every row to the header width.
        $width = $rows ? count($rows[0]) : 0;

        return array_map(fn ($r) => array_pad(array_slice($r, 0, max($width, count($r))), $width, ''), $rows);
    }

    private function first_sheet_path($zip)
    {
        $wb   = @simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'), 'SimpleXMLElement', LIBXML_NONET);
        $rels = @simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'), 'SimpleXMLElement', LIBXML_NONET);
        if ($wb && $rels && isset($wb->sheets->sheet[0])) {
            $rid = (string) $wb->sheets->sheet[0]->attributes('r', true)->id;
            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');

                    return strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
                }
            }
        }

        return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
    }

    private function shared_strings($zip)
    {
        $out = [];
        $xml = @simplexml_load_string((string) $zip->getFromName('xl/sharedStrings.xml'), 'SimpleXMLElement', LIBXML_NONET);
        if ($xml) {
            foreach ($xml->si as $si) {
                $out[] = $this->rich_text($si);
            }
        }

        return $out;
    }

    private function rich_text($node)
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $s = '';
        foreach ($node->r as $r) {
            $s .= (string) $r->t;
        }

        return $s;
    }

    /** Style indexes (cellXfs) whose number format is a date. */
    private function date_styles($zip)
    {
        $xml = @simplexml_load_string((string) $zip->getFromName('xl/styles.xml'), 'SimpleXMLElement', LIBXML_NONET);
        if (! $xml) {
            return [];
        }
        $custom = [];
        if (isset($xml->numFmts)) {
            foreach ($xml->numFmts->numFmt as $f) {
                $code = strtolower(preg_replace('/"[^"]*"|\[[^\]]*\]/', '', (string) $f['formatCode']));
                if (preg_match('/[dy]/', $code)) { // time-only formats (h:mm:ss) have neither d nor y
                    $custom[(int) $f['numFmtId']] = true;
                }
            }
        }
        $out = [];
        $i   = 0;
        if (isset($xml->cellXfs)) {
            foreach ($xml->cellXfs->xf as $xf) {
                $id = (int) $xf['numFmtId'];
                if (($id >= 14 && $id <= 17) || $id === 22 || isset($custom[$id])) {
                    $out[$i] = true;
                }
                $i++;
            }
        }

        return $out;
    }

    private function excel_date($serial)
    {
        // Excel's day 1 = 1900-01-01 (with the 1900 leap-year bug): day 25569 = 1970-01-01.
        return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
    }

    private function col_index($letters)
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }
}
