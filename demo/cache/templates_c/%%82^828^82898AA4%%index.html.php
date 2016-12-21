<?php /* Smarty version 2.6.16, created on 2016-12-20 09:42:07
         compiled from page/index.html */ ?>
<!DOCTYPE html>
<html>
<head lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?php echo $this->_tpl_vars['title']; ?>
</title>

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
    <div class="row">
        <div class="col-md-6">
            <h1 class="text-left text-info">名称：<?php echo $this->_tpl_vars['userInfo']['name']; ?>
</h1>
        </div>
        <div class="col-md-6">
            <h1 class="text-left text-success">uId：<?php echo $this->_tpl_vars['userInfo']['uId']; ?>
</h1>
        </div>

        <div class="col-md-12">
            <?php $_from = $this->_tpl_vars['data']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['item']):
?>
                <div><?php echo $this->_tpl_vars['item']['user_name']; ?>
</div>
            <?php endforeach; endif; unset($_from); ?>
        </div>

    </div>

    <div class="row">
        <ol>
            <li>Item 1</li>
            <li>Item 2</li>
            <li>Item 3</li>
            <li>Item 4</li>
        </ol>

        <ul class="list-inline">
            <li>Item 1</li>
            <li>Item 2</li>
            <li>Item 3</li>
            <li>Item 4</li>
        </ul>

        <dl>
            <dt>Description 1</dt>
            <dd>Item 1</dd>
            <dt>Description 2</dt>
            <dd>Item 2</dd>
        </dl>

        <table class="table table-striped table-bordered table-hover table-condensed">
            <thead>
            <tr>
                <th>#</th>
                <th>Firstname</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>1</td>
                <td>Anna</td>
            </tr>
            <tr>
                <td>2</td>
                <td>Debbie</td>
            </tr>
            <tr>
                <td>3</td>
                <td>John</td>
            </tr>
            </tbody>
        </table>
    </div>
    <blockquote>
        <p>
            这是一个默认的引用实例。这是一个默认的引用实例。这是一个默认的引用实例。这是一个默认的引用实例。这是一个默认的引用实例。这是一个默认的引用实例。这是一个默认的引用实例。这是一个默认的引用实例。
        </p>
    </blockquote>


</div>
</body>
</html>