<?php
require '../functions.php';
$token = RetrieveAndValidateTokenFromRequest();
header('Content-Type: application/json');

$_JSON = json_decode(file_get_contents('php://input'), true);

$username = TokenDeconstruct($token, 'username');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['FormData'])) {
        echo json_encode([
            'stores'   => ChainPDO("SELECT code, name, country FROM stores ORDER BY name")->fetchAll(),
            'sections' => ChainPDO("SELECT code, name FROM sections ORDER BY name")->fetchAll(),
        ]);
    } else if (isset($_GET['products'])) {
        echo json_encode(ChainPDO("SELECT *, PK AS id, DATE_FORMAT(LastScannedOn, '%D-%m-%y at %r') AS LastScannedOn FROM products WHERE location = ?", [$_GET['location']])->fetchAll());
    } else {
        $page  = $_GET['page'];
        $limit = $_GET['limit'];
        $start = $page * $limit;
        
        



        $sort = '';

        $filter = '';

        $CountQuery = "SELECT COUNT(id) FROM locations";

        if (!isset($_GET['sort'])) {
            $sort = 'ORDER BY ' . $_GET['sort'] . ' DESC';
        }

        if (isset($_GET['filter'])) {
            $filter .= ' WHERE ';
            $_filter = urldecode($_GET['filter']);
            $_filter = json_decode($_filter, true)[0];
            //print_r($_filter);die();
            //echo json_encode($_filter);die();

            $property = $_filter['property'];
            $value    = $_filter['value'];
            $operator = $_filter['operator'];

            if ($operator === 'LIKE') {
                $filter .= "`" . $property . "` " . $operator . " '%" . $value . "%'";
            } else if ($operator === 'eq') {
                $filter .= "`" . $property . "` = '" . $value . "'";
                //print_r($_filter);die();
            } else if ($operator === 'rx') {
                $filter .= "`" . $property . "` REGEXP '" . $value . "'";
            }

            /* for ($i = 0; $ioperator) {
                $value    = $filterItem->value;
                $property = $filterItem->property;

                if ($i !== count($_filter) - 1) {
                    $filter .= 'AND';
                }
            } */
            
            $count = $CountQuery . $filter;
        } else {
            $count = $CountQuery;
        }

        function getOperator($operator)
        {
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
                    return '=';
                    break;
                    break;
                case 'noteq':
                case 'notstricteq':
                    return '!=';
                    break;
                case 'like':
                    return 'LIKE';
                    break;
            }
        }

        $started = microtime(true);
        $end          = microtime(true);
        $total_diff   = $end - $started;
        $getqueryTime = number_format($total_diff, 10);

        echo json_encode([
            'data'       => ChainPDO("SELECT
                            *,
                            IF(physicalCount > 0, 1, 0) AS edited, 
                            DATE_FORMAT(modifiedOn, '%D-%b-%Y') AS modifiedOn, 
                            DATEDIFF(now(), modifiedOn) AS daysElapsed, 
                            ABS(physicalCount - systemCount) AS discrepancy 
                            FROM locations $filter AND isVerified = 0 $sort 
                            LIMIT $start, $limit")->fetchAll(),
            'totalCount' => ChainPDO($count)->fetch(PDO::FETCH_COLUMN),
            /* 'cumulativeCount' => ChainPDO("SELECT 
                CAST(SUM(PhysicalCount) AS `Physical Count` AS UNSIGNED),
                CAST(SUM(SystemCount) AS `System Count` AS UNSIGNED),
                CAST(SUM(ABS(PhysicalCount - SystemCount)) AS Discrepancies AS UNSIGNED)
                FROM locations $filter")->fetch() */
            //'counted' => ChainPDO("SELECT COUNT(id) FROM locations WHERE isVerified = 1")->fetch(PDO::FETCH_COLUMN),
            //'uncounted' => ChainPDO("SELECT COUNT(id) FROM locations WHERE isVerified = 0")->fetch(PDO::FETCH_COLUMN),
            //'discrepancies' => ChainPDO("SELECT COUNT(id) FROM locations WHERE ABS(physicalCount - systemCount) > 0")->fetch(PDO::FETCH_COLUMN),

            'cumulativeCount' => ChainPDO("SELECT 
                IFNULL(SUM(IF(isVerified = 1, 1, 0)), 0) AS `Counted`,
                IFNULL(SUM(IF(isVerified = 0, 1, 0)), 0) AS `Not Counted`,
                IFNULL(SUM(ABS(physicalCount - systemCount)), 0) AS `Discrepancies`
                FROM locations $filter")->fetch()
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['Scan'])) {
        if ($_GET['Scan'] === 'location') {
            $code = $_JSON['code'];
    
            $location = ChainPDO("SELECT 
                        *,
                        id,
                        IF(physicalCount > 0, 1, 0) AS edited,
                        DATE_FORMAT(modifiedOn, '%D-%b-%Y') AS modifiedOn,
                        DATEDIFF(now(), modifiedOn) AS daysElapsed,
                        ABS(physicalCount - systemCount) AS discrepancy
                        FROM locations
                        WHERE code = ?
                        AND isVerified = 0
                        ORDER BY id DESC", [$code])->fetch();
            
            if ($location && $location['isVerified'] === 0) {
                echo json_encode($location);
            } else if ($location && $location['isVerified'] === 1) {
                http_response_code(302);
                echo json_encode(['error' => 'Location already scanned']);
            } else {
                http_response_code(409);
                echo json_encode(['error' => 'Location not found']);
            }
        } else if ($_GET['Scan'] === 'product') {
            $id       = $_JSON['id'];
            $barcode  = $_JSON['barcode'];
            $batteryLevel   = $_JSON['batteryLevel'];
            $code     = $_JSON['code'];
            $serialNumber   = $_JSON['serialNumber'];

            $doesExist = ChainPDO("SELECT COUNT(barcode) FROM products WHERE barcode = ?", [$barcode])->fetchColumn();

            if ($doesExist > 0) {
                echo json_encode(['success' => 'Product was found and updated']);

                ChainPDO("UPDATE products SET location = ?, quantity = quantity + 1, lastScannedBy = ?, lastScannedOn = now() WHERE barcode = ?", [$code, $username, $barcode]);
                ChainPDO("UPDATE locations SET systemCount = systemCount + 1, scannedOn = now(), scannedBy = ? WHERE code = ?", [$username, $code]);
                ChainPDO("INSERT INTO scans VALUES (NULL, ?, ?, now(), ?, ?, ?)", [
                    $code,
                    $barcode,
                    $username,
                    $serialNumber,
                    $batteryLevel,
                ]);
            } else {
                ChainPDO("INSERT INTO scans_failed VALUES (NULL, ?, ?, now(), ?, ?, ?)", [
                    $code,
                    $barcode,
                    $username,
                    $serialNumber,
                    $batteryLevel,
                ]);

                $failedScans = ChainPDO("SELECT COUNT(barcode) FROM scans_failed WHERE barcode = ?", [$barcode])->fetchColumn();

                if ($failedScans <= 2) {
                    $doesExist = ChainPDO("SELECT COUNT(barcode) FROM products WHERE barcode = ?", [$barcode])->fetchColumn();

                    if ($doesExist > 0) {
                        echo 'Product was found and updated';
        
                        ChainPDO("UPDATE products SET location = ?, quantity = quantity + 1, lastScannedBy = ?, lastScannedOn = now() WHERE barcode = ?", [$code, $username, $barcode]);
                        ChainPDO("UPDATE locations SET systemCount = systemCount + 1, scannedOn = now(), scannedBy = ? WHERE code = ?", [$username, $code]);
                        ChainPDO("INSERT INTO scans VALUES (NULL, ?, ?, now(), ?, ?, ?)", [
                            $code,
                            $barcode,
                            $username,
                            $serialNumber,
                            $batteryLevel,
                        ]);
                    } else {
                        http_response_code(409);
                        echo json_encode(['error' => 'Retry '. $failedScans .'. This item does not exist in our records.']);
                    }
                } else {
                    http_response_code(409);
                    echo json_encode(['error' => 'This item does not exist in our records.']);
                }
            }
        }
        die();
    }

    if (isset($_GET['ScanProduct'])) {
        $code = $_JSON['code'];

        $location = ChainPDO("SELECT 
            *,
            id,
            IF(physicalCount > 0, 1, 0) AS edited,
            DATE_FORMAT(modifiedOn, '%D-%b-%Y') AS modifiedOn,
            DATEDIFF(now(), modifiedOn) AS daysElapsed,
            ABS(physicalCount - systemCount) AS discrepancy
            FROM locations
            WHERE code = ?
            ORDER BY id DESC",
            [$code])->fetch();
            
            if ($location) {
                echo json_encode($location);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Location not found']);
            }
        die();
    }

    $StoreCode   = $_JSON['store']['code'];
    $StoreName   = $_JSON['store']['name'];
    $country     = $_JSON['store']['country'];
    $SectionCode = $_JSON['section']['code'];
    $SectionName = $_JSON['section']['name'];
    $quantity    = $_JSON['quantity'];

    if ($quantity < 100) {
        $inserts = [];
        $errors = [];

        for ($i = 0; $i < $quantity; $i++) {
            $maxCode = ChainPDO("SELECT MAX(barcode) FROM locations WHERE store = ? AND section = ?", [$StoreName, $SectionName])->fetchColumn();

            if ($maxCode) {
                $count = preg_replace('/[^0-9]/', '', explode('-', $maxCode)[2]);
                $count++;
            } else {
                $count = 1;
            }

            $location = 'L' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $barcode  = $StoreCode . '-' . $SectionCode . '-' . $location;

            try {
                $DB->beginTransaction();

                ChainPDO("INSERT INTO locations VALUES (NULL, ?, ?, ?, ?, 0, 0, 1, ?, now())", [
                    $barcode,
                    $country,
                    $StoreName,
                    $SectionName,
                    $username,
                ]);

                $LastID = $DB->lastInsertId();

                $DB->commit();

                array_push($inserts, ChainPDO("SELECT *, IF(PhysicalCount > 0, 1, 0) AS edited, DATE_FORMAT(ModifiedOn, '%D-%b-%Y') AS ModifiedOn, DATEDIFF(now(), ModifiedOn) AS DaysElapsed, ABS(PhysicalCount - SystemCount) AS discrepancy FROM locations WHERE id = ?", [$LastID])->fetch());
            } catch (PDOException $e) {
                $DB->rollBack();
                array_push($errors, $e->getMessage());
            }
        }

        if (empty($errors)) {
            http_response_code(201);
            echo json_encode($inserts);
        } else {
            http_response_code(500);
            echo json_encode([
                'title' => 'Server Error',
                'content' => 'There was an error creating the locations. Please try again.'
            ]);

            LogToDB(json_encode(
                [
                    'logtype'   => 'ERROR',
                    'operation' => 'DATABASE',
                    'service'   => basename(dirname(__FILE__)),
                    'request'   => file_get_contents('php://input'),
                    'response'  => json_encode($errors),
                ]
            ));
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'title' => 'Exceeded Limit',
            'content' => 'Quantity must be less than 100.'
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (isset($_GET['SubmitScan'])) {
        ChainPDO("UPDATE locations SET isVerified = 1 WHERE id = ?", [$_JSON['id']]);
        echo json_encode(['success' => 'Updated']);
        die();
    }

    if (isset($_GET['ResetPhysicalCount'])) {
        ChainPDO("UPDATE locations SET physicalCount = ? WHERE id = ?", [$_JSON['physicalCount'], $_JSON['id']]);
        echo json_encode(['success' => 'Updated']);
        die();
    }

    $key      = $_JSON['key'];
    $value    = $_JSON['value'];
    ChainPDO("UPDATE locations SET PhysicalCount = ?, ModifiedBy = ?, ModifiedOn = now() WHERE PK = ?", [$value, $username, $key]);

    LogToDB(json_encode(
        [
            'logtype'   => 'INFO',
            'operation' => 'UPDATE',
            'service'   => basename(dirname(__FILE__)),
            'request'   => file_get_contents('php://input'),
            'response'  => 'Physical count updated by ' . $username,
        ]
    ));
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    ChainPDO("UPDATE locations SET systemCount = 0 WHERE id = ?", [$_JSON['id']]);
    ChainPDO("DELETE FROM scans WHERE code = ?", [$_JSON['code']]);

    LogToDB(json_encode(
        [
            'logtype'   => 'INFO',
            'operation' => 'DELETE',
            'service'   => basename(dirname(__FILE__)),
            'request'   => file_get_contents('php://input'),
            'response'  => 'Location scans deleted by ' . $username,
        ]
    ));
}
