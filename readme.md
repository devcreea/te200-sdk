# 这只是为了通用的t200进行开发的SDK包
> 目前有的功能很少，只能修改分机号和批量修改分机号

* 需要在composer中拉取私有库
    ```json
    {
      "repositories": [
        {
          "type": "vcs",
          "url": "http://gitlab.wwagentcc1123.com/tommy/te200-sdk.git"
        }
      ],
      "config": {
        "secure-http": false
      }
    }
```

```php
<?php

namespace Test;

use Te200\ServerProvider;

class Fuck 
{

    public function index()
    {
        // 账号
         $username = 'fuck';

        // 这个密码请查看curl请求加密后的
         $password = 'UGvPYZDkgFK(@lkskF';
         
        // 请求的地址
         $url = 'http://8.8.8.8/cgi/WebCGI?';

        // 初始化
        $te200 = new ServerProvider($url, $username, $password);
        
        // 获取所有列表
        $te200->get();
        
        // 查找单个数据
        $te200->find('test123');

        // 批量修改
        $te200->find('test123')->resetPhoneNumber(['456465']);
        
        // 保存
        $te200->save();

    }

}

```