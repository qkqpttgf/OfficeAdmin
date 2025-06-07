20220717更新：首页为迷惑性登录页面，在这里输入密码是没用的，默认请在`?login=admin`登录，建议修改登录接口。  

跟OneManager一样，可以部署在Heroku/Glitch/SCF/CFC/FC/FG上。

[![Deploy](https://www.herokucdn.com/deploy/button.svg)](https://heroku.com/deploy?template=https://github.com/qkqpttgf/OfficeAdmin)

绑定时，登录微软帐号，授权给程序，  
程序自动帮你创建一个client id，自动添加3个权限，  
添加好权限后，提供一个url给你，点过去后，代表组织管理员同意,  
然后回来点下一步，程序自动创建一个100年的secret，就正常使用了。  

当然也可以手动输入以前创建好的。

# 功能：  
> 搜索用户  
> 新建/删除用户  
> 禁止/允许用户登录  
> 为用户分配订阅许可（直接点击许可栏）  
> 设置/取消全局管理  
> 重置密码（需要以委托权限，所以90天至少访问一次该盘的前台或后台，以获取新token）  

[![Powered by DartNode](https://dartnode.com/branding/DN-Open-Source-sm.png)](https://dartnode.com "Powered by DartNode - Free VPS for Open Source")  
