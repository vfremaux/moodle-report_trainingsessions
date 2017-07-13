<?php
/**
 * Html2Pdf Library - Tests
 *
 * HTML => PDF converter
 * distributed under the OSL-3.0 License
 *
 * @package   Html2pdf
 * @author    Laurent MINGUET <webmaster@html2pdf.fr>
 * @copyright 2017 Laurent MINGUET
 */

namespace Spipu\Html2Pdf\Tests\Tag;

use Spipu\Html2Pdf\Html2Pdf;

/**
 * Class FileNameOkTest
 */
class FileNameOkTest extends \PHPUnit_Framework_TestCase
{
    /**
     * test: the file extension must be PDF
     *
     * @return void
     */
    public function testCase()
    {
        $object = new Html2Pdf();
        $object->pdf->SetTitle('PhpUnit Test');
        $object->writeHTML('Hello World');
        $result = $object->output('test.pdf', 'S');

        $this->assertContains('PhpUnit Test', $result);
    }
}
