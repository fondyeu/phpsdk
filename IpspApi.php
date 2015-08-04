<?php

/**
 * SDK для работы с api платежного шлюза oplata.com.
 */
class IpspApi
{
    /** @var int */
    private $merchant_id;
    /** @var string */
    private $merchant_password;
    /** @var string */
    private $currency;
    /** @var string */
    private $signature;
    /** @var array */
    private $options = array();
    /** @var string */
    private $url;
    /** @var boolean */
    private $debug = false;
    /** @var string */
    private $debug_file;
    /** @var string */
    private $concat;
    /** @var string */
    private $response;

    /** @var string */
    private $baseUrl = 'https://api.oplata.com/api/';

    /** @var array */
    private $apiUrls = array(
        'payment_a' => 'checkout/redirect/',
        'payment_b' => 'checkout/url/',
        'payment_b_step_one' => '3dsecure_step1/',
        'payment_b_step_two' => '3dsecure_step2/',
        'check_status' => 'status/order_id',
        'refund' => 'reverse/order_id',
        'reports' => 'reports/',
        'payment_token' => 'recurring',
        'card_verification' => 'checkout/redirect/',
        'card_credit' => 'p2pcredit/',
        'capture' => 'capture/order_id',
    );

    const RUB = 'RUB';
    const USD = 'USD';
    const GBP = 'GBP';
    const EUR = 'EUR';
    const UAH = 'UAH';

    const JSON = 0;
    const XML = 1;
    const FORM = 2;

    /**
     * @param $merchant_id
     * @param $merchant_password
     */
    public function __construct($merchant_id, $merchant_password)
    {
        $this->merchant_id = $merchant_id;
        $this->merchant_password = $merchant_password;
    }

    /**
     * @return string
     */
    private function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * Установить валюту.
     *
     * @param $currency
     *
     * @return IpspApi $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        if ($this->signature === null) {
            $this->setSignature($this->getOptions());
        }

        return $this->signature;
    }

    /**
     * Задать подпись.
     *
     * @param array $options
     *
     * @return IpspApi $this
     */
    private function setSignature($options = [])
    {
        ksort($options, SORT_STRING);
        $this->concat = implode('|', $options);
        $this->signature = sha1($this->merchant_password.'|'.$this->concat);

        $this->setOption('signature', $this->signature);

        return $this;
    }

    /**
     * Получить параметры.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Очистить параметры.
     *
     * @return IpspApi $this
     */
    private function resetOptions()
    {
        $this->options = array();

        return $this;
    }

    /**
     * Добавить или изменить опцию.
     *
     * @param $name
     * @param $value
     *
     * @return IpspApi $this
     */
    private function setOption($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * @param $name
     *
     * @return $this
     */
    private function setUrl($name)
    {
        $this->url = $this->getBaseUrl().$this->apiUrls[$name];

        return $this;
    }

    /**
     * @return string
     */
    private function getUrl()
    {
        return $this->url;
    }

    /**
     * Получить xml для запроса.
     *
     * @return string
     */
    private function getXmlBody()
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<request>';
        foreach ($this->options as $name => $value) {
            $body .= sprintf('<%s>%s</%s>', $name, $value, $name);
        }

        $body .= '</request>';

        return $body;
    }

    /**
     * Получить json для запроса.
     *
     * @return string
     */
    private function getJsonBody()
    {
        $json = [
            'request' => $this->getOptions(),
        ];

        return json_encode($json);
    }

    /**
     * Параметры по умолчанию для запроса.
     *
     * @param $order_id
     * @param $order_desc
     * @param $amount
     * @param $currency
     *
     * @return $this
     */
    private function setDefaultRequest(
        $order_id,
        $order_desc,
        $amount,
        $currency
    ) {
        $this
            ->resetOptions()
            ->setOption('order_id', $order_id)
            ->setOption('merchant_id', (int) $this->merchant_id)
            ->setOption('order_desc', $order_desc)
            ->setOption('amount', (int) $amount)
            ->setOption('currency', $currency)
        ;

        return $this;
    }

    /**
     * Включен ли debug
     *
     * @return boolean
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * Включить debug
     *
     * @return $this
     */
    public function startDebug()
    {
        $this->debug = true;
        return $this;
    }

    /**
     * Выключить debug
     *
     * @return $this
     */
    public function stopDebug()
    {
        $this->debug = false;
        return $this;
    }

    /**
     * @return string
     */
    private function getDebugFile()
    {
        return $this->debug_file;
    }

    /**
     * Установить файл куда будет записываться debug
     *
     * @param $debug_file
     * @return $this
     */
    public function setDebugFile($debug_file)
    {
        $this->debug_file = $debug_file;
        return $this;
    }

    /**
     * Отправить запрос и получить ответ.
     *
     * @param $url
     * @param int $format
     *
     * @return mixed
     */
    private function send($url, $format = self::JSON)
    {
        $data_string = null;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        switch ($format) {
            case self::JSON:
                $data_string = $this->getJsonBody();
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Content-Length: '.strlen($data_string),
                    ]
                );
                break;
            case self::XML:
                $data_string = $this->getXmlBody();
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/xml',
                        'Content-Length: '.strlen($data_string),
                    ]
                );
                break;
            case self::FORM:
                $options = $this->getOptions();
                $length = count($options);
                $current = 1;
                $data_string = null;
                foreach ($options as $name => $value) {
                    $data_string .= $name.'='.$value;
                    if($current != $length) {
                        $data_string .= '&';
                    }
                    ++$current;
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Content-Length: '.strlen($data_string),
                    ]
                );
                break;
        }

        $result = curl_exec($ch);

        if($this->isDebug()) {
            if($this->getDebugFile() !== null) {
                $this->debug($data_string, $result);
            }
        }

        return $result;
    }

    /**
     * Вести журнал запросов к серверу
     *
     * @param $data_string
     * @param $result
     * @return bool
     */
    private function debug($data_string, $result)
    {
        if(!is_writable($this->getDebugFile())) {
            return false;
        }

        $fhandler = fopen($this->getDebugFile(),'a+');

        $datetime = new \DateTime();

        $fwrite_template = "[%s]\n1) Concatinated string:\n%s\n2) Sended data:\n%s\n3) Returned result:\n%s\n";

        if($fhandler) {
            fwrite(
                $fhandler,
                sprintf($fwrite_template,$datetime,$this->concat,$data_string,$result)
                );
        }
        fclose($fhandler);

        return true;
    }

    /**
     * Создание запроса для перехода на страницу оплаты.
     *
     * @param $order_desc
     * @param $amount
     * @param array $additional
     *
     * @return mixed
     */
    public function payment_a(
        $order_id,
        $order_desc,
        $amount,
        $additional = []
    ) {
        if ($this->getCurrency() === null) {
            $this->setCurrency(self::RUB);
        }
        $currency = $this->getCurrency();

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);

        $this->setDefaultRequest(
            $order_id,
            $order_desc,
            $amount,
            $currency
        );

        foreach ($additional as $name => $value) {
            $this->setOption($name, $value);
        }

        $this
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), self::XML);
    }

    /**
     * Оплата по методу B.
     *
     * @param $order_id
     * @param $order_desc
     * @param $amount
     * @param array $additional
     *
     * @return mixed
     */
    public function payment_b(
        $order_id,
        $order_desc,
        $amount,
        $format = self::XML,
        $additional = []
    ) {
        if ($this->getCurrency() === null) {
            $this->setCurrency(self::RUB);
        }
        $currency = $this->getCurrency();

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);

        $this->setDefaultRequest(
            $order_id,
            $order_desc,
            $amount,
            $currency
        );

        foreach ($additional as $name => $value) {
            $this->setOption($name, $value);
        }

        $this
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), $format);
    }

    /**
     * Оплата по методу B с указанием номера карты. Шаг 1.
     *
     * @param $order_id
     * @param $order_desc
     * @param $amount
     * @param $card_number
     * @param $cvv2
     * @param DateTime $expire_date
     * @param array    $additional
     *
     * @return mixed
     */
    public function payment_b_step_one(
        $order_id,
        $order_desc,
        $amount,
        $card_number,
        $cvv2,
        \DateTime $expire_date,
        $format = self::XML,
        $additional = []
    ) {
        if ($this->getCurrency() === null) {
            $this->setCurrency(self::RUB);
        }
        $currency = $this->getCurrency();

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);

        $this->setDefaultRequest(
            $order_id,
            $order_desc,
            $amount,
            $currency
        );
        $this
            ->setOption('card_number', $card_number)
            ->setOption('cvv2', $cvv2)
            ->setOption('expire_date', $expire_date->format('m.Y'))
        ;

        foreach ($additional as $name => $value) {
            $this->setOption($name, $value);
        }

        $this
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), $format);
    }

    /**
     * Оплата по методу B с указанием номера карты. Шаг 2.
     *
     * @param $order_id
     * @param $pares
     * @param $md
     * @param array  $additional
     * @param string $version
     *
     * @return mixed
     */
    public function payment_b_step_two(
        $order_id,
        $pares,
        $md,
        $additional = [],
        $format = self::XML,
        $version = '1.0'
    ) {
        $this->setOption('order_id', $order_id)
            ->setOption('pares', $pares)
            ->setOption('md', $md)
            ->setOption('version', $version)
            ->setOption('merchant_id', (int) $this->merchant_id)
        ;

        foreach ($additional as $name => $value) {
            $this->setOption($name, $value);
        }

        $this
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), $format);
    }

    /**
     * Получить статус платежа.
     *
     * @param $order_id
     * @param int $format
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function check_status(
        $order_id,
        $format = self::XML
    ) {
        if (null === $order_id) {
            throw new \Exception('Order ID must be defined');
        }

        $this
            ->resetOptions()
            ->setOption('order_id', $order_id)
            ->setOption('merchant_id', (int) $this->merchant_id)
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), $format);
    }

    /**
     * Возврат средств.
     *
     * @param $order_id
     * @param $amount
     * @param string $version
     * @param string $comment
     * @param int    $format
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function refund($order_id, $amount, $version = '1.0', $comment = 'Refund for order', $format = self::XML)
    {
        if (null === $order_id or null === $amount) {
            throw new \Exception('Order ID and amount must be defined');
        }

        if (is_null($this->getCurrency())) {
            $this->setCurrency(self::RUB);
        }
        $currency = $this->getCurrency();

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);

        $this
            ->resetOptions()
            ->setOption('order_id', $order_id)
            ->setOption('amount', $amount)
            ->setOption('currency', $currency)
            ->setOption('version', $version)
            ->setOption('comment', $comment)
            ->setOption('merchant_id', (int) $this->merchant_id)
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), $format);
    }

    /**
     * Генерация выгрузки за период.
     *
     * @param DateTime $dateFrom
     * @param DateTime $dateTo
     * @param int      $format
     * @param string   $version
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function reports(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        $format = self::XML,
        $version = '1.0'
    ) {
        if (null === $dateFrom or null === $dateTo) {
            throw new \Exception('DateFrom And DateTo must be defined');
        }
        if ($format === self::FORM) {
            throw new \Exception('Only XML or JSON formats supported');
        }

        $this
            ->resetOptions()
            ->setOption('date_from', $dateFrom->format('d.m.Y'))
            ->setOption('date_to', $dateTo->format('d.m.Y'))
            ->setOption('merchant_id', (int) $this->merchant_id)
            ->setOption('version', $version)
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), $format);
    }

    /**
     * Оплата с карты по токену.
     *
     * @param $order_id
     * @param $order_desc
     * @param $amount
     * @param $token
     * @param array $additional
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function payment_token(
        $order_id,
        $order_desc,
        $amount,
        $token,
        $additional = []
    ) {
        if (is_null($token)) {
            throw new \Exception('Token must be defined');
        }

        if (is_null($this->getCurrency())) {
            $this->setCurrency(self::RUB);
        }

        $currency = $this->getCurrency();

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);

        $this->setDefaultRequest(
                $order_id,
                $order_desc,
                $amount,
                $currency
            )
            ->setOption('rectoken', $token)
        ;

        foreach ($additional as $name => $value) {
            $this->setOption($name, $value);
        }

        $this
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), self::XML);
    }

    /**
     * Проверка карты.
     *
     * @param $order_id
     * @param $order_desc
     * @param $amount
     * @param array $additional
     *
     * @return mixed
     */
    public function card_verification(
        $order_id,
        $order_desc,
        $amount,
        $additional = []
    ) {
        if (is_null($this->getCurrency())) {
            $this->setCurrency(self::RUB);
        }

        $currency = $this->getCurrency();

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);

        $this->setDefaultRequest(
            $order_id,
            $order_desc,
            $amount,
            $currency
        )
            ->setOption('verification', 'Y')
        ;

        foreach ($additional as $name => $value) {
            $this->setOption($name, $value);
        }

        $this
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), self::XML);
    }

    /**
     * Перевод денег на карту.
     *
     * @param $order_id
     * @param $order_desc
     * @param $amount
     * @param $receiver_card_number
     * @param array $additional
     *
     * @return mixed
     */
    public function card_credit(
        $order_id,
        $order_desc,
        $amount,
        $receiver_card_number,
        $additional = []
    ) {
        if (is_null($this->getCurrency())) {
            $this->setCurrency(self::RUB);
        }

        $currency = $this->getCurrency();

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);

        $this->setDefaultRequest(
                $order_id,
                $order_desc,
                $amount,
                $currency
            )
            ->setOption('receiver_card_number', $receiver_card_number)
        ;

        foreach ($additional as $name => $value) {
            $this->setOption($name, $value);
        }

        $this
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), self::XML);
    }

    /**
     * Списание заблокированной суммы (по order_id).
     *
     * @param $order_id
     * @param $amount
     * @param int    $format
     * @param string $version
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function capture(
        $order_id,
        $amount,
        $format = self::XML,
        $version = '1.0'
    ) {
        if (null === $order_id or null === $amount) {
            throw new \Exception('Order ID and amount must be defined');
        }

        if (is_null($this->getCurrency())) {
            $this->setCurrency(self::RUB);
        }
        $currency = $this->getCurrency();

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);

        $this
            ->resetOptions()
            ->setOption('order_id', $order_id)
            ->setOption('amount', $amount)
            ->setOption('currency', $currency)
            ->setOption('version', $version)
            ->setOption('merchant_id', (int) $this->merchant_id)
            ->setSignature($this->getOptions())
            ->setUrl(__FUNCTION__)
        ;

        return $this->send($this->getUrl(), $format);
    }

    /**
     * Спарсить ответ от сервера
     *
     * @param $response
     * @param $format
     * @param null $itemName
     * @return $this
     */
    public function setResponseItem($response, $format, $itemName = null)
    {
        $array = null;
        switch ($format) {
            case self::XML:
                $xml = new \SimpleXMLElement($response);
                /**
                 * @var $key
                 * @var \SimpleXMLElement $val
                 */
                foreach($xml->children() as $key => $val) {
                    $array[$key] = $val->__toString();
                }
                break;
            case self::JSON:
                $array = json_decode($response,true);
                if(isset($array['response'])) {
                    $array = $array['response'];
                }
                break;
            case self::FORM:
                $preArray = explode('&',$response);
                $array = null;
                foreach ($preArray as $value) {
                    list($name,$val) = explode('=',$value);
                    $array[$name] = urldecode($val);
                }
                break;
        }

        if(!is_null($itemName)) {
            $this->response[$itemName] = $array;
        } else {
            $this->response[] = $array;
        }

        return $this;
    }

    /**
     * Получить ответ как массив
     *
     * @param null $item
     * @return string
     */
    public function getResponse($item = null)
    {
        return is_null($item) ? $this->response : $this->response[$item];
    }

    /**
     * Проверить статус элемента ответа и подпись
     *
     * @param $item
     * @return bool
     */
    public function isApprove($item)
    {
        $item = $this->getResponse($item);
        foreach ($item as $key => $value) {
            if($value === '') {
                unset($item[$key]);
            }
        }

        $oldSignature = $item['signature'];
        unset(
            $item['signature'],
            $item['response_signature_string']
        );

        $newSignature = $this->setSignature($item)->getSignature();

        if($item['order_status'] == 'approved') {
            if($oldSignature == $newSignature) {
                $return = true;
            } else {
                $return = false;
            }
        } else {
            $return = false;
        }

        return $return;
    }
}
