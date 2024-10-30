var NC_INJECT_SELECTION = null;

jQuery(document).ready(function($) {

    if ($("#add_nc_inject_shortcode").length > 0) {

        $("#add_nc_inject_shortcode").click(function(e){
            e.preventDefault();
            var ed;
            if ( typeof(tinymce) != 'undefined' && tinymce.isIE && ( ed = tinymce.get(wpActiveEditor) ) && !ed.isHidden() ) {
                ed.focus();
                ed.windowManager.insertimagebookmark = ed.selection.getBookmark();
            }
            var link = $(this).attr('href');
            var title = $(this).attr('title');
            tb_show(title, link);
        });

    }

    // Fields helper
    if ($('#inject_fields_helper').length != 0){

        // just hide some non sense information
        $('#minor-publishing').hide();

        var $fields_helper = $('#inject_fields_helper');
        var $fields = $('#nc_inject_fields');
        // load current fields
        var fields = $fields.val();
        var tfields = fields.replace(/ /g, '').split(',');
        for (var i=0 ; i < tfields.length ; i++ ) {
            $fields_helper.find('span#ifh_' + tfields[i]).addClass('active');
        }

        $fields_helper.find('span.field')
               .click(function(){
                    var value = $fields.val();
                    var id = $(this).attr('id').substring(4);
                    if ($(this).hasClass('active')) {
                        var re = new RegExp("\\b" + id + "\\b", "g");
                        value = value.replace(re, '');
                    } else {
                        value = value + ',' + id;
                    }
                    $(this).toggleClass('active');
                    value = value.replace(' ', '').replace(',,', ',').replace(/^,/, '').replace(/,$/, '');
                    $fields.val(value);
               })
               .hover(
                    function(){
                        if ( ! $(this).hasClass('active') ) return;
                        var $tip = $('#nc_inject_fields_tip');
                        $tip.html( $(this).attr('title') );
                        $tip.slideDown();
                    },
                    function(){
                        if ( ! $(this).hasClass('active') ) return;
                        $('#nc_inject_fields_tip').hide();
                    }
                );

        $('#ifh_remove_all').click(function(){
            $fields.val('');
            $fields_helper.find('span.field').removeClass('active');
        });


    }

    if ($("#nc_inject_template_toolbar").length > 0) {

        $("#nc_inject_template_toolbar > a").click(function(e){
            e.preventDefault();
            var $menu = $($(this).attr('href').replace('#', '#itt_'));
            if ($menu.is(':visible'))
                $menu.slideUp();
            else {
                $("#nc_inject_template_toolbar .itt_menu")
                    .filter(':visible')
                    .hide();
                $menu.slideDown();
                $menu.mouseleave(function() {$(this).slideUp();});
            }
        });

        $("#nc_inject_template_toolbar .itt_item").click(function(e){
            var id = $(this).attr('id');
            if (id in ITT) {
                if (editor != undefined){
                    var text = ITT[id];
                    if ($(this).hasClass('itt_item_template')) {
                        $.ajax({
                                url: $(this).attr('data-template'),
                                dataType: 'text',
                                success: function(data) {
                                    ncEditorAddText(data);
                                }
                            });
                    } else {
                        ncEditorAddText(text);
                    }
                }
            }
        });

        $("#nc_inject_template_toolbar .itt_item a").click(function(e){
            e.stopPropagation();
        });

    }

});

function ncEditorAddText( text ) {
    if (editor != undefined){
        var selection = editor.session.getTextRange(editor.getSelectionRange());
        if (text.indexOf('[sel]') != -1)
            text = text.replace('[sel]', selection);
        else
            text = selection + text;
        editor.insert(text);
    }
}


// Utility
if ( typeof Object.create !== 'function' ) {
    Object.create = function( obj ) {
        function F() {};
        F.prototype = obj;
        return new F();
    };
}

(function( $, window, document, undefined ) {
    var InjectHelper = {
        init: function( options, elem ) {
            var self = this;

            self.elem = elem;
            self.$elem = $( elem );

            self.options = $.extend( {}, $.fn.injectHelper.options, options );

            self.hideAll();

            self.$elem.find('#ish_template').change( function(e){
                self.templateChange( $(this) );
            });
            self.$elem.find('#ish_type').change( function(e){
                self.typeChange( $(this) );
            });
            self.$elem.find('#ish_category_include, #ish_tag_include, #ish_category_exclude, #ish_tag_exclude').click( function(e){
                self.inexclude( $(this) );
            });
            self.$elem.find('#ish_reset').click( function(e){
                self.reset( $(this) );
            });
            self.$elem.find('#ish_insert').click( function(e){
                self.insert( $(this) );
            });

            // trigger the template change
            self.$elem.find('#ish_template').change();

        },

        templateChange: function( $el ) {
            var self = this;
            self.hideAll();
            if ( $el.find('option:selected').text().indexOf('(s)') != -1 ){
                // solid template
                self.$elem.find('#ish_row_type, #ish_row_status, #ish_row_number, #ish_row_order').hide();
            } else {
                self.$elem.find('#ish_row_type, #ish_row_status, #ish_row_number, #ish_row_order').slideDown();
                self.$elem.find('#ish_type').change();
            }
        },

        typeChange: function( $el ) {
            var self = this;
            self.hideAll();
            switch($el.val()){
                case 'post':
                    self.$elem.find('#ish_row_category, #ish_row_tag, #ish_row_sticky').slideDown(self.pack);
                    break;
                case 'page':
                    self.$elem.find('#ish_row_page').slideDown(self.pack);
                    break;
                case 'attachment':
                    self.$elem.find('#ish_row_attachment').slideDown(self.pack);
                    break;
            }
        },

        inexclude: function( $el ){
            var self = this;
            var prefixe = $el.attr('id').replace(/.*_(.+)_.*/, "$1");
            var type = $el.attr('id').replace(/.*_.*_(.*)/, "$1");
            var id = self.$elem.find('#ish_' + prefixe).val();
            var name = self.$elem.find('#ish_' + prefixe).children("option:selected").text();
            var $list = self.$elem.find('#ish_' + prefixe + '_list');
            // remove if exists
            $list.find( '#ish_' + prefixe + '_element_' + id ).remove();
            // add
            $list.append( '<span id="ish_' + prefixe + '_element_' + id + '" class="ish_' + type + ' button">' + name + '</span>' );
            $list.find( '#ish_' + prefixe + '_element_' + id ).click(function(){$(this).remove();});
            self.pack();
        },

        reset: function( ){
            tb_remove();
        },

        insert: function( ){
            var self = this;
            var out = '';
            // template
            var temp = self.$elem.find('#ish_template').val();
            var solid = ( self.$elem.find('#ish_template option:selected').text().indexOf('(s)') != -1 );
            if (temp) out += ' id="' + temp + '"'
            // debug
            temp = self.$elem.find('#ish_debug').is(":checked");
            out += ' debug="' + (temp ? '1' : '0') + '"';
            // cache
            temp = parseInt(self.$elem.find('#ish_cache').val());
            if (!isNaN(temp)) out += ' cache="' + Math.abs(temp) + '"';
            if (!solid) {
                // post type
                var type = self.$elem.find('#ish_type').val();
                if (type != "") {
                    out += ' post_type="' + type + '"';
                    switch (type) {
                        case "post":
                            var tax = {'category__in':[], 'category__not_in':[], 'tag__in':[], 'tag__not_in':[],};
                            self.$elem.find('#ish_tag_list span, #ish_category_list span').each(function(){
                                var type = $(this).attr('id').replace(/.*_(.+)_.*_.*/, "$1");
                                var val = $(this).attr('id').replace('ish_' + type + '_element_', '');
                                var tax_type = type + '__' + (($(this).hasClass("ish_include")) ? 'in' : 'not_in');
                                //console.log(tax_type, tax);
                                tax[tax_type].push( val );
                            });
                            for (tax_type in tax){
                                if (tax[ tax_type ].length != 0) out += ' ' + tax_type + '="' + tax[ tax_type ].join() + '"';
                            }
                            temp = self.$elem.find('#ish_sticky').val();
                            if (temp != "0") out += ' sticky="' + temp + '"';
                            break;
                        case "page":
                            temp = self.$elem.find('#ish_page').val();
                            if (temp) out += ' post_parent="' + temp + '"';
                            break;
                        case "attachment":
                            temp = self.$elem.find('#ish_attachment').val();
                            if (temp) out += ' post_mime_type="' + temp + '"';
                            break;
                    }
                }
                // status
                temp = self.$elem.find('#ish_status').val();
                if (temp) out += ' post_status="' + temp + '"';
                // number
                temp = parseInt(self.$elem.find('#ish_number').val());
                if (!isNaN(temp)) out += ' posts_per_page="' + temp + '"';
                // order
                temp = self.$elem.find('#ish_orderby').val();
                if (temp) out += ' orderby="' + temp + '"';
                temp = self.$elem.find('#ish_order').val();
                if (temp) out += ' order="' + temp + '"';
            }
            out = '[inject' + out + ' /]';
            window.send_to_editor(out);
        },

        pack: function() {
            $('#TB_ajaxContent').height('auto');
        },
        destroy: function(){
            self.$elem.find('#ish_type, #ish_category_include, #ish_tab_include, #ish_category_exclude, #ish_tab_exclude, #ish_reset, #ish_insert').unbind();
        },

        hideAll: function( ) {
            var self = this;
            self.$elem.find('#ish_row_category, #ish_row_tag, #ish_row_page, #ish_row_attachment, #ish_row_sticky').hide();
        },

        show: function( ) {

        },

    };

    $.fn.injectHelper = function( options ) {
        return this.each(function() {
            var injectHelper = Object.create( InjectHelper );
            injectHelper.init( options, this );

            $.data( this, 'injectHelper', injectHelper );
        });
    };

    $.fn.injectHelper.options = { };

})( jQuery, window, document );

