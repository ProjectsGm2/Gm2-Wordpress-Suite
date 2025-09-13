(function($){
    var Control = elementor.modules.controls.BaseData.extend({
        onReady: function(){
            this.ui = { select: this.$el.find("select") };
            this.fetchOptions();
        },
        fetchOptions: function(){
            var self = this;
            var postType = elementor.config.document.post ? elementor.config.document.post.post_type : '';
            if(!postType){
                return;
            }
            $.post(gm2FieldKey.ajax,{ action:'gm2_field_keys', nonce:gm2FieldKey.nonce, post_type:postType },function(resp){
                if(resp && resp.success){
                    self.updateOptions(resp.data);
                }
            });
        },
        updateOptions: function(list){
            var select = this.ui.select.empty();
            $.each(list,function(key,label){
                select.append($('<option>').val(key).text(label));
            });
            var current = this.getControlValue();
            if(current){ select.val(current); }
        }
    });
    elementor.addControlView('gm2-field-key', Control);
})(jQuery);
