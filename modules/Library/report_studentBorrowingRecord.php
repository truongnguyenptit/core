<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\Library\LibraryReportGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

$_SESSION[$guid]['report_student_emergencySummary.php_choices'] = '';

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Student Borrowing Record'));

if (isActionAccessible($guid, $connection2, '/modules/Library/report_studentBorrowingRecord.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    //Proceed!
    echo '<h2>';
    echo __('Choose Student');
    echo '</h2>';

    $gibbonPersonID = null;
    if (isset($_GET['gibbonPersonID'])) {
        $gibbonPersonID = $_GET['gibbonPersonID'];
    }

    $form = Form::create('action', $_SESSION[$guid]['absoluteURL'].'/index.php', 'get');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', "/modules/".$_SESSION[$guid]['module']."/report_studentBorrowingRecord.php");

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Student'));
        $row->addSelectStudent('gibbonPersonID', $_SESSION[$guid]['gibbonSchoolYearID'])->selected($gibbonPersonID)->placeholder()->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($gibbon->session);

    echo $form->getOutput();

    if ($gibbonPersonID != '') {
        echo '<h2>';
        echo __('Report Data');
        echo '</h2>';

        $gateway = $container->get(LibraryReportGateway::class);
        $criteria = $gateway->newQueryCriteria(true)
                            ->filterBy('gibbonPersonID', $gibbonPersonID);
        $items = $gateway->queryStudentReportData($criteria);
        $table = DataTable::createPaginated('reportdata', $criteria);
        $table
          ->modifyRows(function ($item, $row) {
            if ($item['status'] == 'On Loan') {
                return $item['pastDue'] == 'Y' ? $row : $row->addClass('error');
            }
            return $row;
          });
        $table
          ->addExpandableColumn('details')
          ->format(function ($item) {
            $detailTable = "<table>";
            $fields = unserialize($item['fields']);
            foreach (unserialize($item['typeFields']) as $typeField) {
                $detailTable .= sprintf('<tr><td><b>%1$s</b></td><td>%2$s</td></tr>', $typeField['name'], $fields[$typeField['name']]);
            }
            $detailTable .= '</table>';
            return $detailTable;
          });
        $table
          ->addColumn('image')
          ->format(function ($item) {
            return Format::photo($item['imageLocation'], 240);
          });
        $table
          ->addColumn('name', __('Name (Author/Producer)'))
          ->format(function ($item) {
            return sprintf('<b>%1$s</b><br/>%2$s', $item['name'], Format::small($item['producer']));
          });
        $table
          ->addColumn('id', __('ID'))
          ->format(function ($item) {
            return sprintf('<b>%1$s</b>', $item['id']);
          });
        $table
          ->addColumn('location', __('Location'))
          ->format(function ($item) {
            return sprintf('<b>%1$s</b><br/>%2$s', $item['spaceName'], Format::small($item['locationDetail']));
          });
        $table
          ->addColumn('borrowDate', __('Return Date (Borrow Date)'))
          ->format(function ($item) {
            return sprintf('<b>%1$s</b><br/>%2$s', $item['status'] == 'On Loan' ? Format::date($item['returnExpected']) : 'N/A', Format::small(Format::date($item['timestampOut'])));
          });
        $table
          ->addColumn('status', __('Status'));
        echo $table->render($items);
    }
}
