jQuery(function ($) {
    var scripts = {
        init: function () {
            this.addCard();
            this.removeCard();
        },
        addCard: function(){

            $('#addTinkoffCard').click(function() {

                var data = {
                    'action': 'addTinkoffCard'
                }
    
                $.ajax( {
                    url: wp_ajax.ajax_url,
                    type: 'POST',
                    data: data,
                    async: false,
                    success : function(response){
                        response = $.parseJSON(response);

                        if ( response.url ){
                            // window.location.replace(response.url);
                            window.open(response.url, '_blank');
                        }else{
                            console.log('Error: ' + response.error);
                        }
                    }
                });
              
            });
        },
        removeCard: function(){

            $('#removeTinkoffCard').click(function() {

                var data = {
                    'action': 'removeTinkoffCard'
                }
    
                $.ajax( {
                    url: wp_ajax.ajax_url,
                    type: 'POST',
                    data: data,
                    async: false,
                    success : function(response){
                        response = $.parseJSON(response);

                        if ( response.url ){
                            window.location.replace(response.url);
                        }else{
                            console.log('Error: ' + response.error);
                        }
                    }
                });
              
            });
        }
    }
    scripts.init();
});