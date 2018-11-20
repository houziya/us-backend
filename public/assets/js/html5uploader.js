// Us 后台上传活动照片

$(function() {
    var userId = $('.userId').text();
    var eventId = $('.eventId').text();
    var state = 'pending';
    var momentId = null;
    var pictureList = [];
    var version = 2;

    function buildDom() {
        var imageLiAmount = Math.ceil($('.gnineh li').length / 5);
        $('.gnineh').height(imageLiAmount * 170 + 60 + 'px');
    }

    function confirmDelete(obj) {
        var thisLiIndex = obj.index();
        var thisLiTop = obj.css('top');
        var thisLiLeft = obj.css('left');
        var MaxLi = $('.upload-imageli').length;
        for(var i = thisLiIndex; i < MaxLi-1; i++) {
            var readyMoveLi = $('.upload-imageli').eq(i+1);
            var tt = readyMoveLi.css('top');
            var ll = readyMoveLi.css('left');
            readyMoveLi.css({'top': thisLiTop, 'left': thisLiLeft});
            thisLiTop = tt;
            thisLiLeft = ll;
        }
        obj.remove();
        buildDom();
    }

    var uploader = WebUploader.create({
        auto: false,
        server: 'uploadPicture',
        pick: '#btnChoose',
        accept: {
            title: 'Images',
            extensions: 'jpg,jpeg,bmp,png',
            mimeTypes: 'image/*'
        },
        thumb: {
            width: 500,
            height: 500,
            // 图片质量，只有type为`image/jpeg`的时候才有效。
            quality: 70,
            // 是否允许放大，如果想要生成小图的时候不失真，此选项应该设置为false.
            allowMagnify: true,
            // 是否允许裁剪。
            crop: false,
            // 为空的话则保留原有图片格式。
            // 否则强制转换成指定的类型。
            type: 'image/jpeg'
        },
        formData: {
            userId: userId,
            eventId: eventId,
            version: version
        },
        compress: false
    });

    uploader.on('fileQueued',function(file){
        var $li = $(
                '<li id="' + file.id + '" class="upload-imageli file-item thumbnail">' +
                '<img alt="">'+
                '<div class="info">'+
                '<i class="icon-lock" style="display: none;"> </i> <span style="color: orange;display: none;">锁定活动照片</span>'+
                '<button id="imgContent" class="imgContent btn btn-primary">描述</button>'+
                '<button id="imgRemove" class="imgRemove btn btn-primary">删除</button>'+
                '</div>'+
                '</li>'
            ),
            $img = $li.find('img');
        $('.gnineh').append($li);

        uploader.makeThumb( file, function( error, src ) {
            if ( error ) {
                $img.replaceWith('<span>不能预览</span>');
                return;
            }

            $img.attr( 'src', src );
        });
        $('#btnUpload').css('display','block');
        startupload = 1;

        buildDom();

        //删除照片
        $('.imgRemove').on('click', function(e) {
            e.preventDefault();
            var $thisImageWillBeDelete = $(this).parent().parent('.upload-imageli');
            var deleteId = $(this).parent().parent('.upload-imageli').attr('id');
            confirmDelete($thisImageWillBeDelete);
            uploader.removeFile(deleteId, true);
        });
    });

    uploader.on('startUpload', function() {
        $.ajax({
            type: 'post',
            url: 'ajaxCreateMoment',
            async: false,
            dataType: 'json',
            data: {'userId': userId, 'eventId': eventId},
            success: function (res) {
                data = $.parseJSON(res);
                momentId = res.momentId;
            }
        });
    });

    uploader.on('uploadBeforeSend',function(object,data,headers){
        data.momentId = momentId;
        data.size = object.file._info.width + 'x' + object.file._info.height;
        data.shoot_time = object.file.lastModifiedDate.getTime();
        console.log(data);
    });

    // 文件上传过程中创建进度条实时显示。
    uploader.on('uploadProgress', function(file, percentage) {
        var $li = $('#'+file.id),
            $percent = $li.find('.progress span');

        // 避免重复创建
        if ( !$percent.length ) {
            $percent = $('<p class="progress"><span></span></p>')
                .appendTo( $li )
                .find('span');
        }

        $percent.css( 'width', percentage * 100 + '%' );
    });

    // 文件上传成功，给item添加成功class, 用样式标记上传成功。
    uploader.on( 'uploadSuccess', function(file, response) {
        pictureList.push(response.pictureList);
    });

    // 文件上传失败，显示上传出错。
    uploader.on( 'uploadError', function( file ) {
        alert('失败');
    });

    // 完成上传完了，成功或者失败，先删除进度条。
    uploader.on('uploadComplete', function(file) {
        $('#'+file.id).find('.progress').remove();
    });

    uploader.on('uploadFinished', function() {
        $.ajax({
            type: 'post',
            url: 'commitMoment',
            dataType: 'json',
            data: {'userId': userId, 'eventId': eventId, 'momentId': momentId, 'pictureList': pictureList, 'version': version},
            success : function(res) {
                $('#btnUpload').hide();
                location.href = 'appendPicture';
                //alert('当前活动 ID 为: '+res.p.eventId+' ;当前活动共上传图片: '+res.p.total_upload_count+' 张;当前动态 ID 为: '+res.p.momentId+' ;本次共上传图片: '+res.p.last_upload_count+' 张.');
            }
        });
    });

    uploader.on('all', function(type) {
        if (type === 'startUpload') {
            state = 'uploading';
        } else if (type === 'stopUpload') {
            state = 'paused';
        } else if (type === 'uploadFinished') {
            state = 'done';
        }

        if (state === 'uploading') {
            $('#btnUpload').text('暂停上传');
        } else {
            $('#btnUpload').text('开始上传');
        }
    });

    $('#btnUpload').on('click', function() {
        if (state === 'uploading') {
            uploader.stop();
        } else {
            uploader.upload();
        }
    });

});
