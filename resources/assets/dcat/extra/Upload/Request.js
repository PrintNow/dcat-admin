
export default class Request {
    constructor(Uploader) {
        this.uploader = Uploader;
    }

    delete(file, callback) {
        let _this = this,
            parent = _this.uploader,
            options = parent.options,
            uploader = parent.uploader;

        Dcat.confirm(parent.lang.trans('confirm_delete_file'), file.serverId, function () {
            var post = options.deleteData;

            post.key = file.serverId;

            if (! post.key) {
                parent.input.delete(file.serverId);

                return uploader.removeFile(file);
            }

            post._column = parent.getColumn();
            post._relation = parent.relation;

            Dcat.loading();

            $.post({
                url: options.deleteUrl,
                data: post,
                success: function (result) {
                    Dcat.loading(false);

                    if (result.status) {
                        callback(result);

                        return;
                    }

                    parent.helper.showError(result)
                }
            });

        });
    }

    // 保存已上传的文件名到服务器
    update() {
        let _this = this,
            parent = _this.uploader,
            uploader = parent.uploader,
            options = parent.options,
            updateColumn = parent.getColumn(),
            relation = parent.relation,
            values = parent.input.get(), // 获取表单值
            num = uploader.getStats().successNum,
            form = $.extend({}, options.formData);

        if (!num || !values || !options.autoUpdateColumn) {
            return;
        }

        if (relation) {
            // 关联表单（hasMany、embeds 等）中的文件不单独自动保存，随整个表单提交时一并保存：
            // 后端的嵌套表单更新需要完整的子表记录数据，只提交单个字段会被当作新记录处理
            return;
        }

        form[updateColumn] = values.join(',');

        delete form['_relation'];
        delete form['upload_column'];

        $.post({
            url: options.updateServer,
            data: form,
        });
    }
}