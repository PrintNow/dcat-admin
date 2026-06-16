<div id="web-artisan-app" style="display:flex;gap:0;height:calc(100vh - 200px);min-height:500px;border:1px solid #dee2e6;border-radius:4px;overflow:hidden;">

    {{-- 左侧面板 --}}
    <div id="wa-left" style="width:35%;min-width:280px;max-width:420px;display:flex;flex-direction:column;border-right:1px solid #dee2e6;background:#fff;overflow:hidden;">

        {{-- 搜索框 --}}
        <div style="padding:12px;border-bottom:1px solid #dee2e6;">
            <input id="wa-search" type="text" class="form-control form-control-sm"
                   placeholder="搜索命令..." autocomplete="off">
        </div>

        {{-- 命令列表 --}}
        <div id="wa-command-list" style="flex:1;overflow-y:auto;padding:8px 0;">
            <div class="text-center text-muted py-3" style="font-size:13px;">
                <i class="fa fa-spinner fa-spin"></i> 加载命令列表...
            </div>
        </div>

        {{-- 命令详情 & 参数表单 --}}
        <div id="wa-form-area" style="border-top:1px solid #dee2e6;padding:12px;overflow-y:auto;max-height:45%;display:none;">
            <div id="wa-cmd-desc" class="text-muted mb-2" style="font-size:12px;"></div>
            <div id="wa-args-form"></div>
            <div class="mt-2">
                <label class="mb-1" style="font-size:12px;font-weight:600;">运行模式</label>
                <div>
                    <label class="mr-3" style="font-size:13px;cursor:pointer;">
                        <input type="radio" name="wa-mode" value="foreground" checked> 前台运行
                    </label>
                    <label style="font-size:13px;cursor:pointer;">
                        <input type="radio" name="wa-mode" value="background"> 后台运行 (nohup)
                    </label>
                </div>
            </div>
            <button id="wa-run-btn" class="btn btn-primary btn-sm mt-2 w-100" disabled>
                <i class="fa fa-play mr-1"></i> 运行
            </button>
        </div>
    </div>

    {{-- 右侧面板 --}}
    <div id="wa-right" style="flex:1;display:flex;flex-direction:column;overflow:hidden;background:#fff;">

        {{-- Tabs --}}
        <div style="border-bottom:1px solid #dee2e6;padding:0 16px;background:#f8f9fa;">
            <ul class="nav nav-tabs border-0" style="margin-bottom:-1px;">
                <li class="nav-item">
                    <a class="nav-link active wa-tab" href="#" data-tab="output"
                       style="padding:10px 16px;font-size:13px;">当前输出</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link wa-tab" href="#" data-tab="logs"
                       style="padding:10px 16px;font-size:13px;">历史日志</a>
                </li>
            </ul>
        </div>

        {{-- 当前输出面板 --}}
        <div id="wa-panel-output" style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
            <div id="wa-status-bar" style="padding:6px 14px;font-size:12px;color:#6c757d;background:#f8f9fa;border-bottom:1px solid #dee2e6;display:none;">
                <span id="wa-status-cmd" style="font-family:'JetBrains Mono',monospace;"></span>
                <span id="wa-status-info" class="ml-2"></span>
            </div>
            <pre id="wa-output"
                 style="flex:1;margin:0;padding:16px;overflow:auto;background:#1e1e2e;color:#cdd6f4;font-family:'JetBrains Mono',monospace;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-all;border-radius:0;"><span style="color:#585b70;">// 选择左侧命令后点击「运行」，输出将显示在此处</span></pre>
        </div>

        {{-- 历史日志面板 --}}
        <div id="wa-panel-logs" style="flex:1;display:none;flex-direction:column;overflow:hidden;">
            <div style="padding:10px 14px;border-bottom:1px solid #dee2e6;background:#f8f9fa;display:flex;align-items:center;gap:8px;">
                <button id="wa-refresh-logs" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-refresh"></i> 刷新
                </button>
                <span id="wa-log-hint" class="text-muted" style="font-size:12px;"></span>
            </div>
            <div style="flex:1;display:flex;overflow:hidden;">
                <div id="wa-log-list" style="width:45%;border-right:1px solid #dee2e6;overflow-y:auto;padding:0;">
                </div>
                <pre id="wa-log-content"
                     style="flex:1;margin:0;padding:16px;overflow:auto;background:#1e1e2e;color:#cdd6f4;font-family:'JetBrains Mono',monospace;font-size:12px;line-height:1.6;white-space:pre-wrap;word-break:break-all;border-radius:0;"><span style="color:#585b70;">// 点击左侧日志文件查看内容</span></pre>
            </div>
        </div>
    </div>
</div>

<script>
Dcat.ready(function () {
    const BASE = '{{ $baseUrl }}';
    const CSRF = $('meta[name="csrf-token"]').attr('content');

    let allCommands = [];
    let selectedCommand = null;

    // ─── 加载命令列表 ───────────────────────────────────────────
    function loadCommands() {
        fetch(BASE + '/commands', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(r => r.json())
            .then(data => {
                allCommands = data;
                renderCommandList(data);
            })
            .catch(() => {
                $('#wa-command-list').html('<div class="text-danger px-3 py-2" style="font-size:12px;">加载命令失败</div>');
            });
    }

    // ─── 渲染命令分组列表 ──────────────────────────────────────
    function renderCommandList(cmds) {
        const groups = {};
        cmds.forEach(cmd => {
            const ns = cmd.name.includes(':') ? cmd.name.split(':')[0] : '_root';
            (groups[ns] = groups[ns] || []).push(cmd);
        });

        if (Object.keys(groups).length === 0) {
            $('#wa-command-list').html('<div class="text-muted px-3 py-2" style="font-size:12px;">无匹配命令</div>');
            return;
        }

        let html = '';
        Object.entries(groups).sort(([a], [b]) => a.localeCompare(b)).forEach(([ns, items]) => {
            const label = ns === '_root' ? '(global)' : ns;
            const nsId  = 'wa-ns-' + ns.replace(/[^a-z0-9]/gi, '_');
            html += `<div class="wa-group">
                <div class="wa-group-header d-flex align-items-center px-3 py-1"
                     style="cursor:pointer;font-size:11px;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.6px;user-select:none;"
                     data-target="#${nsId}">
                    <i class="fa fa-angle-down mr-1 wa-chevron" style="font-size:10px;transition:transform .15s;"></i>${label}
                    <span class="badge badge-secondary ml-auto" style="font-size:10px;">${items.length}</span>
                </div>
                <div id="${nsId}" class="wa-group-body">`;
            items.forEach(cmd => {
                html += `<div class="wa-cmd-item px-3 py-1"
                              data-name="${cmd.name}"
                              style="cursor:pointer;font-size:13px;font-family:'JetBrains Mono',monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                              title="${escHtml(cmd.description || '')}">
                    ${escHtml(cmd.name)}
                </div>`;
            });
            html += `</div></div>`;
        });

        $('#wa-command-list').html(html);
        bindCommandListEvents();
    }

    function bindCommandListEvents() {
        // 分组折叠
        $('.wa-group-header').on('click', function () {
            const target = $($(this).data('target'));
            const chevron = $(this).find('.wa-chevron');
            target.slideToggle(150);
            chevron.css('transform', target.is(':visible') ? '' : 'rotate(-90deg)');
        });

        // 选中命令
        $('.wa-cmd-item').on('click', function () {
            $('.wa-cmd-item').removeClass('active').css('background', '');
            $(this).addClass('active').css('background', '#e8f4fd');
            const name = $(this).data('name');
            selectedCommand = allCommands.find(c => c.name === name);
            renderCommandForm(selectedCommand);
        });
    }

    // ─── 渲染参数/选项表单 ─────────────────────────────────────
    function renderCommandForm(cmd) {
        if (!cmd) return;
        $('#wa-cmd-desc').text(cmd.description || '');

        const def = cmd.definition || {};
        const argsList = def.arguments ? Object.values(def.arguments) : [];
        const optsList  = def.options  ? Object.values(def.options)  : [];

        let html = '';

        if (argsList.length) {
            html += '<div class="mb-2"><strong style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Arguments</strong></div>';
            argsList.forEach(arg => {
                const required = arg.is_required;
                html += `<div class="form-group mb-1">
                    <label style="font-size:12px;margin-bottom:2px;">
                        ${escHtml(arg.name)}${required ? ' <span class="text-danger">*</span>' : ''}
                        <small class="text-muted">${escHtml(arg.description || '')}</small>
                    </label>
                    <input type="text" class="form-control form-control-sm wa-arg"
                           data-argname="${escHtml(arg.name)}"
                           placeholder="${arg.default !== null && arg.default !== undefined ? escHtml(String(arg.default)) : ''}" />
                </div>`;
            });
        }

        const skipOpts = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'];
        const filteredOpts = optsList.filter(o => !skipOpts.includes(o.name));

        if (filteredOpts.length) {
            html += '<div class="mb-2 mt-2"><strong style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Options</strong></div>';
            filteredOpts.forEach(opt => {
                const acceptsValue = opt.accept_value;
                if (acceptsValue) {
                    html += `<div class="form-group mb-1">
                        <label style="font-size:12px;margin-bottom:2px;">
                            --${escHtml(opt.name)}
                            <small class="text-muted">${escHtml(opt.description || '')}</small>
                        </label>
                        <input type="text" class="form-control form-control-sm wa-opt-val"
                               data-optname="${escHtml(opt.name)}"
                               placeholder="${opt.default !== null && opt.default !== undefined ? escHtml(String(opt.default)) : ''}" />
                    </div>`;
                } else {
                    html += `<div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input wa-opt-bool"
                               id="opt_${escHtml(opt.name)}" data-optname="${escHtml(opt.name)}">
                        <label class="form-check-label" for="opt_${escHtml(opt.name)}" style="font-size:12px;">
                            --${escHtml(opt.name)}
                            <small class="text-muted">${escHtml(opt.description || '')}</small>
                        </label>
                    </div>`;
                }
            });
        }

        if (!argsList.length && !filteredOpts.length) {
            html = '<p class="text-muted" style="font-size:12px;">此命令无参数/选项</p>';
        }

        $('#wa-args-form').html(html);
        $('#wa-form-area').show();
        $('#wa-run-btn').prop('disabled', false);
    }

    // ─── 收集表单参数 ──────────────────────────────────────────
    function collectFormData() {
        const args = [];
        $('.wa-arg').each(function () {
            const v = $(this).val().trim();
            if (v) args.push(v);
        });

        const opts = {};
        $('.wa-opt-val').each(function () {
            const v = $(this).val().trim();
            if (v) opts[$(this).data('optname')] = v;
        });
        $('.wa-opt-bool').each(function () {
            if ($(this).is(':checked')) opts[$(this).data('optname')] = true;
        });

        return {args, opts};
    }

    // ─── 运行命令 ─────────────────────────────────────────────
    $('#wa-run-btn').on('click', function () {
        if (!selectedCommand) return;

        const mode    = $('input[name="wa-mode"]:checked').val();
        const {args, opts} = collectFormData();
        const btn     = $(this);

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> 运行中...');
        switchTab('output');

        const endpoint = mode === 'background' ? BASE + '/run-background' : BASE + '/run';
        const t0 = Date.now();

        if (mode === 'foreground') {
            $('#wa-output').html('<span style="color:#585b70;">// 执行中，请稍候...</span>');
            $('#wa-status-bar').hide();
        } else {
            $('#wa-output').html('<span style="color:#585b70;">// 命令已提交后台运行，请稍后在「历史日志」查看输出...</span>');
            $('#wa-status-bar').hide();
        }

        fetch(endpoint, {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({command: selectedCommand.name, args, opts}),
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                $('#wa-output').html('<span style="color:#f38ba8;">错误: ' + escHtml(data.error) + '</span>');
            } else if (mode === 'foreground') {
                const exitColor = data.exit_code === 0 ? '#a6e3a1' : '#f38ba8';
                $('#wa-output').text(data.output || '(无输出)');
                $('#wa-status-bar').show();
                $('#wa-status-cmd').text('$ ' + selectedCommand.name);
                $('#wa-status-info').html(
                    `<span style="color:${exitColor};">exit ${data.exit_code}</span>` +
                    ` &nbsp;耗时 ${data.elapsed}s`
                );
                scrollOutputToBottom();
            } else {
                $('#wa-output').html(
                    '<span style="color:#a6e3a1;">✔ 命令已在后台启动</span>\n' +
                    '<span style="color:#585b70;">日志文件: ' + escHtml(data.log_file || '') + '</span>'
                );
                $('#wa-status-bar').hide();
            }
        })
        .catch(err => {
            $('#wa-output').html('<span style="color:#f38ba8;">请求失败: ' + escHtml(String(err)) + '</span>');
        })
        .finally(() => {
            btn.prop('disabled', false).html('<i class="fa fa-play mr-1"></i> 运行');
        });
    });

    function scrollOutputToBottom() {
        const pre = document.getElementById('wa-output');
        if (pre) pre.scrollTop = pre.scrollHeight;
    }

    // ─── 搜索过滤 ─────────────────────────────────────────────
    $('#wa-search').on('input', function () {
        const q = $(this).val().trim().toLowerCase();
        if (!q) {
            renderCommandList(allCommands);
        } else {
            renderCommandList(allCommands.filter(c =>
                c.name.toLowerCase().includes(q) ||
                (c.description || '').toLowerCase().includes(q)
            ));
        }
        if (selectedCommand) {
            const $item = $('.wa-cmd-item[data-name="' + selectedCommand.name + '"]');
            if ($item.length) $item.addClass('active').css('background', '#e8f4fd');
        }
    });

    // ─── Tab 切换 ─────────────────────────────────────────────
    function switchTab(tab) {
        $('.wa-tab').removeClass('active');
        $(`.wa-tab[data-tab="${tab}"]`).addClass('active');
        if (tab === 'output') {
            $('#wa-panel-output').css('display', 'flex');
            $('#wa-panel-logs').hide();
        } else {
            $('#wa-panel-output').hide();
            $('#wa-panel-logs').css('display', 'flex');
            loadLogList();
        }
    }

    $('.wa-tab').on('click', function (e) {
        e.preventDefault();
        switchTab($(this).data('tab'));
    });

    // ─── 历史日志 ─────────────────────────────────────────────
    function loadLogList() {
        $('#wa-log-list').html('<div class="text-center text-muted py-3" style="font-size:12px;"><i class="fa fa-spinner fa-spin"></i></div>');
        fetch(BASE + '/logs', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(r => r.json())
            .then(data => {
                if (!data.length) {
                    $('#wa-log-list').html('<div class="text-muted px-3 py-2" style="font-size:12px;">暂无日志</div>');
                    $('#wa-log-hint').text('');
                    return;
                }
                $('#wa-log-hint').text(data.length + ' 条日志');
                let html = '';
                data.forEach(log => {
                    const size = log.size > 1024 ? (log.size / 1024).toFixed(1) + ' KB' : log.size + ' B';
                    html += `<div class="wa-log-item d-flex align-items-center px-3 py-2"
                                  data-file="${escHtml(log.name)}"
                                  style="cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:12px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-family:'JetBrains Mono',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(log.name)}">${escHtml(log.name)}</div>
                            <div class="text-muted" style="font-size:11px;">${escHtml(log.modified)} &middot; ${size}</div>
                        </div>
                        <button class="btn btn-xs btn-outline-danger wa-del-log ml-2" data-file="${escHtml(log.name)}" style="flex-shrink:0;padding:1px 5px;font-size:11px;">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>`;
                });
                $('#wa-log-list').html(html);
                bindLogListEvents();
            })
            .catch(() => {
                $('#wa-log-list').html('<div class="text-danger px-3 py-2" style="font-size:12px;">加载失败</div>');
            });
    }

    function bindLogListEvents() {
        $('.wa-log-item').on('click', function (e) {
            if ($(e.target).closest('.wa-del-log').length) return;
            const file = $(this).data('file');
            $('.wa-log-item').css('background', '');
            $(this).css('background', '#e8f4fd');
            loadLogContent(file);
        });

        $('.wa-del-log').on('click', function (e) {
            e.stopPropagation();
            const file = $(this).data('file');
            if (!confirm('确定删除日志 ' + file + ' ？')) return;
            fetch(BASE + '/logs?' + new URLSearchParams({file}), {
                method:  'DELETE',
                headers: {'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest'},
            })
            .then(() => loadLogList())
            .catch(() => Dcat.error('删除失败'));
        });
    }

    function loadLogContent(file) {
        $('#wa-log-content').html('<span style="color:#585b70;">// 加载中...</span>');
        fetch(BASE + '/logs/content?' + new URLSearchParams({file}), {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                $('#wa-log-content').html('<span style="color:#f38ba8;">' + escHtml(data.error) + '</span>');
            } else {
                $('#wa-log-content').text(data.content || '(空文件)');
                const pre = document.getElementById('wa-log-content');
                if (pre) pre.scrollTop = pre.scrollHeight;
            }
        })
        .catch(() => {
            $('#wa-log-content').html('<span style="color:#f38ba8;">加载失败</span>');
        });
    }

    $('#wa-refresh-logs').on('click', loadLogList);

    // ─── 工具函数 ─────────────────────────────────────────────
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ─── 初始化 ───────────────────────────────────────────────
    loadCommands();
});
</script>
