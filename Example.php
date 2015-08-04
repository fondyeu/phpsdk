<?php
require_once __DIR__.'/IpspApi.php';

$oplataApi = new IpspApi(
    1000,
    'test'
);

$oplataApi->setCurrency(IpspApi::RUB);

//echo $oplataApi
//    ->payment_a('test3600040034', 'Test payment', '100')
//;

//echo $oplataApi->check_status('test01', IpspApi::JSON);

//echo $oplataApi->refund('test01','10');

//$dateFrom = new \DateTime();
//$dateFrom->add(\DateInterval::createFromDateString('-3 days'));
//
//echo $oplataApi->reports($dateFrom, new \DateTime(), IpspApi::JSON);

//echo $oplataApi->capture('test01','10.00',  IpspApi::JSON);


$status = $oplataApi->check_status('test3600040034', IpspApi::FORM);

$oplataApi->setResponseItem($status, IpspApi::FORM, 'status');

var_dump($oplataApi->isApprove('status'));