<?php
// 应用公共文件
function show_json($status = 0, $msg = "", $data = [])
{
    header("Content-Type: application/json");
    echo json_encode([
        'code' => $status,
        'msg' => $msg,
        'data' => $data,
    ]);
    die;
}


function make_no()
{
    $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
    $orderSn = $yCode[intval(date('Y')) - 2017] . strtoupper(dechex(date('m'))) . date(
            'd') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf(
            '%02d', rand(0, 99));
    return $orderSn;
}