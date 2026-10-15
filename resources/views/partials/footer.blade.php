{{--
    默认页脚：保持框架原有行为（Powered by 版权行 + 回到顶部按钮）。

    应用侧想替换或彻底去掉这个默认页脚，不需要改框架代码，
    只要在自己的项目里放一份同名视图覆盖即可：

        resources/views/vendor/admin/partials/footer.blade.php

    （视图名仍然是 admin::partials.footer。）

    注意事项：
      - <footer class="main-footer ..."> 容器不建议删掉：框架
        resources/assets/adminlte/js/ControlSidebar.js 会读它的 outerHeight()；
      - 里面的 .scroll-top 按钮由 dcat 的 JS 控制显隐，class 不要改。
--}}
<footer class="main-footer pt-1">
    <p class="clearfix blue-grey lighten-2 mb-0 text-center">
        <span class="text-center d-block d-md-inline-block mt-25">
            Powered by
            <a target="_blank" href="https://github.com/gy23rm/dcat-admin">Dcat Admin</a>
            <span>&nbsp;·&nbsp;</span>
            v{{ Dcat\Admin\Admin::VERSION }}
        </span>

        <button class="btn btn-primary btn-icon scroll-top pull-right" style="position: fixed;bottom: 2%; right: 10px;display: none">
            <i class="feather icon-arrow-up"></i>
        </button>
    </p>
</footer>
