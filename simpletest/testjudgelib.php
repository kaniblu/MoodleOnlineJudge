<?php
/**
 * Unit tests for (some of) the main features.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package local
 */
 
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}
 
// access to use global variables.
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// Make sure the code being tested is accessible.
require_once($CFG->dirroot . '/local/onlinejudge/judgelib.php'); // Include here to ensure set_config()

/** This class contains the test cases for the functions in judegelib.php. */
class local_onlinejudge_test extends UnitTestCase {
	function setUp() {
        global $DB, $CFG;

        $this->realDB = $DB;
        $dbclass = get_class($this->realDB);
        $DB = new $dbclass();
        $DB->connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname, $CFG->unittestprefix);

        if ($DB->get_manager()->table_exists('onlinejudge_tasks')) {
            $DB->get_manager()->delete_tables_from_xmldb_file($CFG->dirroot . '/local/onlinejudge/db/install.xml');
        }
        $DB->get_manager()->install_from_xmldb_file($CFG->dirroot . '/local/onlinejudge/db/install.xml');

        if ($DB->get_manager()->table_exists('files')) {
            $table = new xmldb_table('files');
            $DB->get_manager()->drop_table($table);
            $table = new xmldb_table('config_plugins');
            $DB->get_manager()->drop_table($table);
        }
        $DB->get_manager()->install_one_table_from_xmldb_file($CFG->dirroot . '/lib/db/install.xml', 'files');
        $DB->get_manager()->install_one_table_from_xmldb_file($CFG->dirroot . '/lib/db/install.xml', 'config_plugins');
        set_config('maxmemlimit', 64*1024*1024, 'local_onlinejudge');
        set_config('maxcpulimit', 10, 'local_onlinejudge');
        set_config('ideonedelay', 5, 'local_onlinejudge');
	}

	function tearDown() {
		global $DB, $CFG;
        $DB = $this->realDB;
	}

    function triger_test($language, $files, $input, $output, $cpulimit, $memlimit, $expect) {
        global $DB;

        $options->input = $input;
        $options->output = $output;
        $options->cpulimit = $cpulimit;
        $options->memlimit = $memlimit;

        $taskid = onlinejudge_submit_task(1, 1, $language, $files, $options);
        $task = onlinejudge_judge($taskid);
        $DB->update_record('onlinejudge_tasks', $task);

        $this->assertEqual($task->status, $expect);
    }

	function test_accepted() {
        $files = array('/test.c' => '
#include <stdio.h>

int main(void)
{
    int c;
    while ( (c = getchar()) != EOF)
        putchar(c);
    return 0;
}
        ');
        $this->triger_test('c_sandbox', $files, 'hello', 'hello', 1, 1024*1024, ONLINEJUDGE2_STATUS_ACCEPTED);
	}

	function test_memlimit() {
        $files = array('/test.c' => '
                  #include <stdlib.h>

                 int main(void)
                 {
                     int *p = malloc(1024*1024*1024);
                     free(p);

                     return 0;
                  }
                  ');
        $this->triger_test('c_sandbox', $files, '', '', 1, 1024*1024, ONLINEJUDGE2_STATUS_MEMORY_LIMIT_EXCEED);
	}

	function test_wrong_answer() {
        $files = array('/test.c' => '
#include <stdio.h>

int main(void)
{
    int c;
    while ( (c = getchar()) != EOF)
        putchar(c-1);
    return 0;
}
        ');
        $this->triger_test('c_sandbox', $files, 'hello', 'hello', 1, 1024*1024, ONLINEJUDGE2_STATUS_WRONG_ANSWER);
	}

	function test_presentation_error() {
        $files = array('/test.c' => '
#include <stdio.h>

int main(void)
{
    int c;
    while ( (c = getchar()) != EOF) {
        putchar(c);
        if (c == \' \')
            putchar(c);
    }
    return 0;
}
        ');
        $this->triger_test('c_sandbox', $files, 'hello world', 'hello world', 1, 1024*1024, ONLINEJUDGE2_STATUS_PRESENTATION_ERROR);
	}

	function test_cpulimit() {
        $files = array('/test.c' => '
#include <stdio.h>

int main(void)
{
    for(;;);
    return 0;
}
        ');
        $this->triger_test('c_sandbox', $files, '', '', 1, 1024*1024, ONLINEJUDGE2_STATUS_TIME_LIMIT_EXCEED);
	}

	function test_compilation_error() {
        $files = array('/test.c' => '
#include <stdio.h>

int main(void)
{
    for(;;);
    return 0;
}
        ');
        $this->triger_test('c_warn2err_sandbox', $files, '', '', 1, 1024*1024, ONLINEJUDGE2_STATUS_COMPILATION_ERROR);
	}

	function test_fork() {
        $files = array('/test.c' => '
#include <stdio.h>
#include <unistd.h>

int main(void)
{
    fork();
    return 0;
}
        ');
        $this->triger_test('cpp_sandbox', $files, '', '', 1, 1024*1024, ONLINEJUDGE2_STATUS_RESTRICTED_FUNCTIONS);
	}

	function test_fopen() {
        $files = array('/test.c' => '
#include <stdio.h>

int main(void)
{
    fopen("/etc/passwd", "r");
    return 0;
}
        ');
        $this->triger_test('c_sandbox', $files, '', '', 1, 1024*1024, ONLINEJUDGE2_STATUS_RESTRICTED_FUNCTIONS);
	}

}
