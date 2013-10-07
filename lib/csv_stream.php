<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * A FileStream extension class to do specific handling of CSV files.
 */
class CsvStream extends FileStream {
    /**
     * @var array a place holder for the column names
     */
    private $keys;
    /**
     * @var int A zero-based number indicating the row containing the column names
     */
    private $keys_index;
    /**
     * Class constructor.
     *
     * @param string $file The path to the CSV file name.
     * @param string $mode The same mode options passed to fopen().
     * @param int $keys_index The row number where the column names are defined.
     * @throws CsvStreamException
     */
    public function __construct($file, $mode='r', $keys_index=0) {
        if (!file_exists($file) && in_array($mode, array('r', 'w', 'a'))) {
            throw new CsvStreamException($file, ' does not exists.');
        } elseif (in_array($mode, array('r', 'w', 'a'))) {
            $mimes = array('text/csv', 'text/plain', 'application/vnd.ms-excel', 'text/x-comma-separated-values');
            $mime = new FileMimeType($file);
            if (!in_array($mime . '', $mimes)) {
                throw new CsvStreamException($file, $mime);
            }
        }
        parent::__construct($file, $mode);
        $this->keys_index = $keys_index;
        $i = 0;
        do {
            if ($i == $keys_index) {
                $this->keys = $this->getcsv();
            }
        } while ($i++ <= $keys_index);
    }
    /**
     * Writes a CSV line to file
     *
     * @param array $line The data to be written in a single CSV line
     */
    public function writeline(array $line) { $this->putcsv($line); }
    /**
     * Reads a CSV line from file
     *
     * @return array The single-line data read from a CSV file
     */
    public function readline() {
        $data = $this->getcsv(); 
        return ($data !== FALSE) ? array_combine($this->keys, $data) : FALSE;
    }
    /**
     * Writes CSV lines to file
     *
     * @param array $lines The multi-dimensional data to be written as CSV lines
     */
    public function write(array $lines) {
        foreach ($lines as $line) {
            $this->writeline($line);
        }
    }
    /**
     * Search the CSV given a column name and a value that matches the column
     *
     * @param string $key The column name
     * @param mixed $value The value we are looking for.
     * @return array The row matching the query.
     */
    public function search($key, $value) {
        $this->rewind();
        $i = 0;
        while (($row = $this->getcsv()) !== FALSE) {
            if ($i > $this->keys_index) {
                $record = array_combine($this->keys, $row);
                if ($record[$key] == $value) {
                    return $record;
                }
            }
            $i++;
        }
        return NULL;
    }
}

class CsvStreamException extends \Exception {

    public function __construct($file, $errmsg='is not a CSV file.') {
        parent::__construct("$file $errmsg");
    }
}
?>