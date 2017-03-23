<?php /* Smarty version 2.6.16, created on 2017-01-13 13:53:21
         compiled from login.html */ ?>
<!DOCTYPE html>
<html>
<head lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>登录系统</title>

    <!-- 新 Bootstrap 核心 CSS 文件 -->
    <link rel="stylesheet" href="/static/css/bootstrap.min.css">

    <!-- 可选的Bootstrap主题文件（一般不用引入） -->
    <link rel="stylesheet" href="/static/css/bootstrap-theme.min.css">

    <!-- jQuery文件。务必在bootstrap.min.js 之前引入 -->
    <script src="http://cdn.bootcss.com/jquery/1.11.1/jquery.min.js"></script>

    <!-- 最新的 Bootstrap 核心 JavaScript 文件 -->
    <script src="/static/js/bootstrap.min.js"></script>
</head>
<body>
<div class="container">
    <div class="row" style="padding-top:100px;">
        <div class="col-md-4 col-md-offset-4">
            <div class="login-panel panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title text-center">登录</h3>
                </div>
                <div class="panel-body">
                    <form role="form" action="/User/login" method="post">
                        <fieldset>
                            <div class="form-group">
                                <input class="form-control" placeholder="账号" name="username" type="text" autofocus>
                            </div>
                            <div class="form-group">
                                <input class="form-control" placeholder="密码" name="password" type="password" value="">
                            </div>
                            <!-- Change this to a button or input when using this as a form -->
                            <input type="submit" class="btn btn-lg btn-success btn-block" value="登录"/>
                            <?php if ($this->_tpl_vars['msg']): ?>
                            <div class="form-group"><?php echo $this->_tpl_vars['msg']; ?>
</div>
                            <?php endif; ?>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>