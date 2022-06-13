<?php
require '../../../functions.php';
//require '../../../../libraries/PHPSpreadSheet/vendor/autoload.php';
require '/var/www/html/api/libraries/PHPSpreadSheet/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

Tokenify($_GET['token'], false);
$endpoint = 'https://foresee-technologies.com/';

/*$scans = ChainPDO("SELECT LocationBarcode, ProductBarcode, ScannedOn, ScannedBy FROM scans")->fetchAll();

foreach ($scans as $scan) {
    echo $scan['LocationBarcode'] . '<br/>';
    ChainPDO("UPDATE products SET location = ?, LastScannedOn = ?, LastScannedBy = ? WHERE barcode = ?", [$scan['LocationBarcode'], $scan['ScannedOn'], $scan['ScannedBy'], $scan['ProductBarcode']]);
}
die();*/


if ($_GET['context'] === 'display') {
    /* $page  = $_GET['page'];
    $limit = $_GET['limit'];
    $start = $page * $limit; */
    $order = '';
    $sort = '';
    $filter = '';
    $limit = '';

    $countQuery = "SELECT COUNT(id) FROM scans";

    if (isset($_GET['order'])) {
        $order = $_GET['order'] == 1 ? ' DESC' : ' ASC';
    }

    if (isset($_GET['sort'])) {
        $sort = 'ORDER BY ' . $_GET['sort'];
    }

    if (isset($_GET['filter'])) {
        $filter = ' WHERE ';
        $_filter = urldecode($_GET['filter']);
        $_filter = json_decode($_filter, true);

        foreach ($_filter as $key => $value) {
            $key++;
            $operator = $value['operator'];
            $property = $value['property'];
            $value    = $value['value'];

            switch ($operator) {
                case 'lt':
                    return '<';
                    break;
                case 'gt':
                    return '>';
                    break;
                case '<=':
                    return 'lteq';
                    break;
                case '>=':
                    return 'gteq';
                    break;
                case 'eq':
                case 'stricteq':
                    $filter .= "`".$property."` = '".$value."'";
                    break;
                case 'noteq':
                case 'notstricteq':
                    return '!=';
                    break;
                case 'like':
                    $filter .= "`".$property."` LIKE '%".$value."%'";
                    break;
                case 'rx':
                    $filter .= "SUBSTR(code, 1, 5) REGEXP '".$value."'";
                    break;
            }

            if ($key !== count($_filter)) {
                $filter .= ' AND ';
            }
        }
        
        $count = $countQuery . $filter;

        $filterChunk = explode(' AND ', $filter)[0];
    } else {
        $count = $countQuery;
    }

    if (isset($_GET['limit'])) {
        $page  = isset($_GET['page']) ? $_GET['page'] : 0;
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
        $start  = $page * $limit;
        $limit = ' LIMIT ' . $start . ', ' . $limit;
    }

    $scans = ChainPDO("SELECT id, code, barcode, scannedBy, serialNumber, batteryLevel, DATE_FORMAT(scannedOn, '%a, %D %b %Y at %r') AS scannedOn FROM scans $filter $sort $order $limit")->fetchAll();

    $dataset = [];

    foreach ($scans as $key => $scan) {
        $selection = ChainPDO("SELECT name, quantity, color, length, section FROM products WHERE location = ?", [$scan['code']])->fetch();
        $obj_merged = (object) array_merge((array) $scan, (array) $selection);
        array_push($dataset, $obj_merged);
    }

    echo json_encode([
        'data'       => $dataset,
        'totalCount' => ChainPDO($count)->fetch(PDO::FETCH_COLUMN),
    ]);
} else {
    $storeId = $_GET['storeId'];
    /* $dataset_ = ChainPDO("SELECT id, code, scans.barcode, COUNT(code) AS quantity
    FROM scans
    WHERE storeId = ? 
    GROUP BY code
    ORDER BY id DESC", [$storeId])->fetchAll();

    $dataset = [];

    foreach ($dataset_ as $key => $value) {
        $selection = ChainPDO("SELECT name, color, length, section FROM products WHERE location = ?", [$value['code']])->fetch();
        //$name = $selection['name'];
        //$obj_merged = (object) array_merge((array) $value, (array) $selection);
        $data = [
            'code'        => $value['code'],
            'barcode'     => $value['barcode'],
            'quantity'     => $value['quantity'],
            'section'     => empty($selection['section']) ? '' : $selection['section'],
            'name'        => empty($selection['name']) ? '' : $selection['name'],
            'length'      => empty($selection['length']) ? '' : $selection['length'],
            'color'       => empty($selection['color']) ? '' : $selection['color']
        ];
        array_push($dataset, $data);
    } */
    //echo json_encode($dataset);die();
    
    //print_r($dataset);die();
    /* $dataset = ChainPDO("SELECT id, code, scans.barcode, scannedBy, serialNumber, batteryLevel, DATE_FORMAT(scannedOn, '%a, %D %b %Y at %r') AS scannedOn, name, COUNT(code) AS quantity, color, length, section
    FROM scans 
    INNER JOIN products
    ON scans.code = products.location
    WHERE storeId = ? 
    GROUP BY code
    ORDER BY id DESC", [$storeId])->fetchAll(); */

    $dataset = ChainPDO("SELECT SUBSTR(code, 1, 5) AS code, section, SpecialCode, color, size, name, scans.barcode, COUNT(code) AS quantity
        FROM scans
        LEFT JOIN products
        ON scans.barcode = products.barcode
        WHERE storeId = ?
        GROUP BY scans.barcode, code
        ORDER BY code", [$storeId])->fetchAll();
    
    $count = count($dataset);

    $path = API_ROOT . 'docs/generated/';

    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    $filename = basename(dirname(__FILE__)) . '.' . date('d-m-Y.H:i:s') . '.xlsx';
    $path     = $path . $filename;

    ini_set('memory_limit', '-1');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Accept-Ranges: bytes');
    header('Cache-Control: max-age=0');
    header('Content-Disposition: attachment; filename=' . $filename);

    $rangeHeaders = 'A3:' . range('A', 'Z')[count($dataset[0]) - 1] . '3';
    $rangeDataset = 'A4:' . range('A', 'Z')[count($dataset[0]) - 1] . ($count + 3);

    $arrayData = [
        [
            'Location',
            'Department',
            'Special Code',
            'Color',
            'Size',
            'Description',
            'SKU',
            'Qty (EA)',
        ],
    ];

    $sum = 0;

    for ($i = 0; $i < $count; $i++) {
        array_push($arrayData, [
            $dataset[$i]['code'],
            $dataset[$i]['section'],
            $dataset[$i]['SpecialCode'],
            $dataset[$i]['color'],
            $dataset[$i]['size'],
            $dataset[$i]['name'],
            $dataset[$i]['barcode'],
            $dataset[$i]['quantity'],
        ]);

        $sum += $dataset[$i]['quantity'];
    }

    $styleArrayHeaders = [
        'font'      => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
        ],
        'borders'   => [
            'bottom' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                'color'       => ['argb' => '30D5C8'],
            ],
        ],
        'fill'      => [
            'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
            'rotation'   => 90,
            'startColor' => [
                'argb' => '30D5C8',
            ],
            'endColor'   => [
                'argb' => 'FFFFFFFF',
            ],
        ],
    ];

    $styleArrayDataset = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color'       => ['argb' => '000000'],
            ],
        ],
    ];

    $spreadsheet = new Spreadsheet();
    $drawing     = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setName('Logo');
    $drawing->setDescription('Logo');
    $drawing->setPath('/var/www/html/img/logo.jpg');
    $drawing->setHeight(50);
    $drawing->setOffsetX(5);
    $drawing->setOffsetY(5);
    $drawing->setWorksheet($spreadsheet->getActiveSheet());
    $spreadsheet->getActiveSheet()->getCell('A2')->setValue("Foresee Technologies
Report: Individual Product Scans
Total: $sum"
    );
    $spreadsheet->getActiveSheet()->getStyle('A2')->getAlignment()->setWrapText(true);
    $spreadsheet->getActiveSheet()->getStyle('A2')->getFont()->setSize(10);
    $spreadsheet->getActiveSheet()->getStyle('A2')->getFont()->setItalic(true);
    $spreadsheet->getActiveSheet()->getStyle('A2')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
    $spreadsheet->getActiveSheet()->getStyle('A2')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $spreadsheet->getActiveSheet()->getStyle('D3:L3')->getAlignment()->setWrapText(true);
    $spreadsheet->getActiveSheet()->fromArray($arrayData, null, 'A3');
    $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(10);
    $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(15);
    $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(15);
    $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(25);
    $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(7);
    $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(30);
    $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(15);
    $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(10);
    $spreadsheet->getActiveSheet()->getStyle($rangeHeaders)->applyFromArray($styleArrayHeaders);
    $spreadsheet->getActiveSheet()->getStyle($rangeDataset)->applyFromArray($styleArrayDataset);
    $spreadsheet->getActiveSheet()->mergeCells('A1:C1');
    $spreadsheet->getActiveSheet()->mergeCells('A2:D2');
    $spreadsheet->getActiveSheet()->getStyle('G')->getNumberFormat()->setFormatCode('#');
    $spreadsheet->getActiveSheet()->setAutoFilter($rangeHeaders);
    $spreadsheet->getActiveSheet()->getRowDimension(1)->setRowHeight(45);
    $spreadsheet->getActiveSheet()->getRowDimension(2)->setRowHeight(60);
    $spreadsheet->getActiveSheet()->getRowDimension(3)->setRowHeight(20);
    $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
    $spreadsheet->getDefaultStyle()->getFont()->setSize(11);

    $writer = new Xlsx($spreadsheet);
    $writer->save($path);
    readfile($path);
}


/* $started = microtime(true);
$end          = microtime(true);
$total_diff   = $end - $started;
$getqueryTime = number_format($total_diff, 10); */