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

            // 解析输入内容，去掉空白与千分位分隔符，无法解析时返回 null
            function toNumber(val) {
                val = String(val === undefined || val === null ? '' : val).replace(/[\s,]/g, '');

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
            var lastValid = toNumber(clone.val());

            function getVal() {
                var n = toNumber(clone.val());

                return n === null ? (lastValid === null ? 0 : lastValid) : n;
            }

            function setText(n) {
                clone.val(n);
                lastValid = n;
                clone.trigger('change');
            }

            // 与原逻辑保持一致：按钮按整数加减
            function step(delta) {
                clone.focus();
                setText(clamp(parseInt(getVal()) + delta));
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

            // 输入过程中不拦截按键（允许粘贴、快捷键、输入法等），失焦时再统一校验
            clone.prop('type', 'text').blur(function () {
                var raw = $.trim(clone.val());

                if (raw === '') {
                    lastValid = null;

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

                var cleaned = raw.replace(/[\s,]/g, '');

                if (clamp(n) !== n) {
                    setText(clamp(n));
                } else if (cleaned !== raw) {
                    // 仅去掉空白与千分位分隔符，其余保持用户输入原样
                    setText(cleaned);
                } else {
                    lastValid = n;
                }
            });


            self.replaceWith(group);
        });
    };
}(jQuery));
