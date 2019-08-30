<?php
/**
 * Created by PhpStorm.
 * User: cree
 * Date: 2019/5/16
 * Time: 16:17
 */

namespace Te200;


class ServerProvider extends TeBase
{
    // voip的列表字符串，需要解析的
    protected $voidListStr = null;

    // provider主要的电话修改字段
    protected $key = 'dodsetting';

    // 修改信息
    protected $updateMsg = [];

    /**
     * 初始化
     *
     * ServerProvider constructor.
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function __construct($ip, $username, $password)
    {
        parent::__construct();

        $this->setLogin($ip, $username, $password);

        // 初始化
        $this->voidListStr = $this->getVoidLists();
    }

    /**
     * 获取void的列表
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getVoidLists() {
        $listData= [
            'cookies' => $this->baseCookie(),
        ];
        $url = $this->baseUrl(1510);
        $listRequest = $this->client->request('get', $url, $listData);

        $html = (string)$listRequest->getBody();

        $pattern = '/<input type="hidden" id="MyPBX_COMM" value="(.*?)">/';
        preg_match($pattern, $html, $newHtml);

        if (empty($newHtml[1])) throw new \Exception('1.请检查网络是否正常2.检查账号密码是否正确3.检查您的列表是否存在');

        $newHtml[1] = trim($newHtml[1], '&');

        return $this->getServerProviders($newHtml);
    }

    /**
     * 返回当前数组
     *
     * @param $column
     * @return $this|bool
     */
    public function find($column)
    {

        if (is_array($column)) return $this->findArray($column);

        return $this->findArray([$column]);

    }


    /**
     * 查询数组
     *
     * @param array $array
     * @return $this
     */
    private function findArray(array $array)
    {

        $new_array = array_filter($this->datas, function ($val) use ($array) {
            if (in_array($val['providername'], $array)) return $val;
        });
        $this->datas = $new_array;

        return $this;

    }



    /**
     * 返回服务提供者的数组
     *
     * @param $str
     * @return array
     */
    public function getServerProviders($str)
    {

        $providers = null;
        $key = 'ServiceProvider:';
        $typeDataArray = explode('&', $str[1]);

        foreach ($typeDataArray as $value) {
            if (strpos($value, $key) !== false) {
                $providers = trim($value, $key);
            }
        }

        $providersArray = explode(';', $providers);

        array_pop($providersArray);

        foreach ($providersArray as $key => $val) {
            $temp = explode(',', $val);
            $providersArray[$key] = $this->mergeParam($temp);
        }
        $this->datas = $providersArray;
        $this->explodeDod();

        return $providersArray;
        //
    }

    /**
     * 获取Dod数据 [手机号 - 对应分机号]
     *
     * @param $array
     * @return bool|$this
     */
    public function explodeDod()
    {

        foreach ($this->datas as $key => &$values) {

            if (empty($values[$this->key])) {continue;}

            $providerData = explode('-', $values[$this->key]);
            $newData = array_map(function ($val) {

                $dod = explode('@',$val);

                // 别乱改上下顺序，不然死的是你
                return [
                    'phone' => $dod[0],
                    'extension_number' => $dod[1],
                ];
            }, $providerData);

            $values[array_search($values[$this->key], $values)] = $newData;
        }
        return $this;
    }


    /**
     * 合并数据
     *
     * @return $this
     */
    public function implodeDod()
    {

        foreach ($this->datas as $key => &$values) {

            if (empty($values[$this->key])) continue;

            $new_data = [];
            foreach ($values[$this->key] as &$val) {
                $new_data[] = implode('@', $val);
            }

            $values[$this->key] = implode('-', $new_data);

            return $this;
        }
    }

    /**
     * 修改数据 号码
     * @param $extension ?分机号
     * @param $phone ?分机号绑定的号码
     * @return ServerProvider
     * @throws \Exception
     */
    public function editDod($extension, $phone)
    {
        // 循环datas中的数据

        foreach ($this->datas as $key => $val) {
            if ($this->updateDod($key, $extension, $phone)) return $this;
        }

        throw new \Exception('该号码不存在，没有修改成功', 404);

    }

    /**
     * 循环去更改
     *
     * @param $key
     * @param $extension
     * @param $phone
     * @return ServerProvider|bool
     */
    private function updateDod($key, $extension, $phone)
    {
        // 看起来太长，缩短一点
        $data =& $this->datas[$key];
        if (empty($data[$this->key])) return false;

        foreach ($data[$this->key] as &$val){

            if ($val['extension_number'] == $extension) {

                $this->setUpdateMsg($extension, $val['phone'], $phone);

                $val['phone'] = $phone;
                return $this;
            }
        }

        return false;
    }

    public function count()
    {
        $count = 0;
        array_walk($this->datas, function ($val) use (&$count) {
            $count += count($val[$this->key]);
        });

        return $count;
    }


    /**
     * 替换所有的手机号
     * @param array $phones
     * @return ServerProvider
     * @throws \Exception
     */
    public function resetPhoneNumber(array $phones)
    {

        $count = $this->count();

        if ($count != count($phones)) throw new \Exception("手机号数量不正确，应该传入：{$count}个号码"  , 422);

        foreach ($this->datas as $index => &$data) {

            $datas =& $data[$this->key];

            foreach ($datas as $key => $val) {

                $value = array_shift($phones);
                if (empty($value)) throw new \Exception('要切换的手机号为空', 422);

                $this->setUpdateMsg($val['extension_number'], $val['phone'], $value);

                $datas[$key]['phone'] = $value;
            }

        }
        return $this;

    }

    /**
     * 保存这次的结果
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function save()
    {
        // 合并数据
        $this->implodeDod();

        $cookie = $this->baseCookie([
            'curUrl' => 1510,
            'applychange' => 'true',
        ]);

        $listData= [
            'cookies' => $cookie,
        ];

        $url = $this->baseUrl(1514);


        foreach ($this->datas as $val) {

            $listData['form_params'] = $val;

            $listRequest = $this->client->request('post', $url, $listData);
            if ($listRequest->getStatusCode() != 200) {
                throw new \Exception('保存失败', $listRequest->getStatusCode());
            }
        }
        $this->applySave();
        return $this->getUpdateMsg();

    }

    /**
     * 返回提交的参数 合并
     *
     * @param $vals
     * @return array
     */
    private function mergeParam($vals)
    {


        $array = [
            'providername' => $vals[0],
            'hostip' => $vals[1],
            'trunkport' => $vals[2],
            'maxchannel' => $vals[3],
            'transport' => $vals[5],
            'qualify' => $vals[6],
            'dtmfmode' => $vals[7],
            'globaldod' => $vals[8],
            'dodsetting' => $vals[9],
            'codecs' => $vals[4],
            'type' => 1
        ];

        return $array;

    }

    /**
     * 保存生效
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function applySave()
    {
        $cookie = $this->baseCookie([
            'curUrl' => 1510,
            'applychange' => '',
        ]);

        $listData= [
            'cookies' => $cookie,
        ];

        $url = $this->baseUrl(1606);

        $listData['form_params'] = [
            'username' => 'admin'
        ];

        $listRequest = $this->client->request('post', $url, $listData);

        if ($listRequest->getStatusCode() != 200) {
            throw new \Exception('保存失败', $listRequest->getStatusCode());
        }

    }

    /**
     * 获取更改信息
     *
     * @return array
     */
    public function getUpdateMsg()
    {
        return $this->updateMsg;
    }

    public function setUpdateMsg($extension_number, $last_phone, $new_phone)
    {
        $this->updateMsg[] = [
            'extension_number' => $extension_number,
            'last_phone' => $last_phone,
            'new_phone' => $new_phone
        ];
    }

    /**
     * 更改globaldod
     * @param $number
     * @return $this
     */
    public function setGlobaldod($column)
    {
        $count = null;
        if (is_array($column)) {
            $count = count($column);
        } else {
            $column = [$column];
            $count = 1;
        }

        $datas_count = count($this->datas);

        if ($count != $datas_count) throw new \Exception("全局手机号数量不正确，应该传入：{$datas_count}个号码"  , 422);

        $datas = $this->datas;

        foreach ($datas as $key => $val) {
            $datas[$key]['globaldod'] = array_shift($column);
        }
        $this->datas = $datas;

        return $this;
    }

}
