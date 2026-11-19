/* ========================================================================
 * bootstrap-spin - v1.0
 * https://github.com/wpic/bootstrap-spin
 * ========================================================================
 * Copyright 2014 WPIC, Hamed Abdollahpour
 *
 * ========================================================================
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ========================================================================
 */

(function ($) {

    $.fn.bootstrapNumber = function (options) {

        var settings = $.extend({
            upClass: 'default',
            downClass: 'default',
            center: true
        }, options);

        return this.each(function (e) {
            var self = $(this);
            var clone = self.clone();

            var min = toNumber(self.attr('min'));
            var max = toNumber(self.attr('max'));

            // 规范化输入：全角数字/符号转半角，去掉空白与千分位分隔符
            function clean(val) {
                return String(val === undefined || val === null ? '' : val)
                    .replace(/[\uff10-\uff19\uff0b\uff0d\uff0e]/g, function (c) {
                        return String.fromCharCode(c.charCodeAt(0) - 0xfee0);
                    })
                    .replace(/[\s,\uff0c]/g, '');
            }

            // 解析数值，无法解析时返回 null
            function toNumber(val) {
                val = clean(val);

                if (val === '' || ! /^[-+]?(\d+\.?\d*|\.\d+)$/.test(val)) {
                    return null;
                }

                return parseFloat(val);
            }

            function clamp(n) {
                if (min !== null && n < min) {
                    return min;
                }
                if (max !== null && n > max) {
                    return max;
                }

                return n;
            }

            // 最近一次合法值，输入非法时回退到该值
            var lastValid = toNumber(clone.val()) === null ? null : clean(clone.val());

            function getVal() {
                var n = toNumber(clone.val());

                if (n === null) {
                    n = toNumber(lastValid);
                }

                return n === null ? 0 : n;
            }

            function setText(n) {
                clone.val(n);
                lastValid = n;
                clone.trigger('change');
            }

            // 与原逻辑保持一致：按钮按整数加减，结果超出 min/max 时不做任何变动
            function step(delta) {
                var n = parseInt(getVal()) + delta;

                if (clamp(n) !== n) {
                    return;
                }

                clone.focus();
                setText(n);
            }

            // 开始编辑时同步当前值，避免其它脚本通过 val() 修改后仍回退到旧值
            function syncLastValid() {
                var val = clone.val();

                if ($.trim(val) === '') {
                    lastValid = null;
                } else if (toNumber(val) !== null) {
                    lastValid = clean(val);
                }
            }

            // 失焦或回车时校验输入值
            function normalize() {
                var raw = $.trim(clone.val());

                if (raw === '') {
                    // 与原逻辑保持一致：空值按 0 处理，0 超出 min/max 时取边界值，否则保持为空
                    if (clamp(0) !== 0) {
                        setText(clamp(0));
                    } else {
                        lastValid = null;
                    }

                    return;
                }

                var n = toNumber(raw);

                if (n === null) {
                    // 无法解析时回退到上一次的合法值
                    if (lastValid === null) {
                        clone.val('');
                    } else {
                        setText(lastValid);
                    }

                    return;
                }

                var cleaned = clean(raw),
                    // 与原逻辑保持一致：按整数部分判断是否超出 min/max
                    limited = clamp(parseInt(n));

                if (limited !== parseInt(n)) {
                    setText(limited);
                } else if (cleaned !== clone.val()) {
                    // 仅做半角转换并去掉空白与千分位分隔符，其余保持用户输入原样
                    setText(cleaned);
                } else {
                    lastValid = cleaned;
                }
            }

            var group = $("<div class='input-group'></div>");
            var down = $("<button type='button'>-</button>").attr({'class': 'btn btn-' +settings.downClass, 'disabled': options.disabled}).click(function () {
                step(-1);
            });
            var up = $("<button type='button'>+</button>").attr({'class': 'btn btn-' +settings.upClass, 'disabled': options.disabled}).click(function () {
                step(1);
            });
            $("<span class='input-group-btn'></span>").append(down).appendTo(group);
            clone.appendTo(group);
            if (clone) {
                clone.css('text-align', 'center');
            }
            $("<span class='input-group-btn'></span>").append(up).appendTo(group);

            // 输入过程中不拦截按键（允许粘贴、快捷键、输入法等），失焦时再统一校验；
            // 回车会直接提交表单而不触发失焦，所以回车时也先校验一次
            clone.prop('type', 'text').focus(syncLastValid).blur(normalize).keydown(function (e) {
                if (e.keyCode === 13) {
                    normalize();
                }
            });

            self.replaceWith(group);
        });
    };
}(jQuery));
