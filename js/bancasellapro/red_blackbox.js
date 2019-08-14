;
/**
 * Created by Massimo Maino on 31/10/16.
 */
// basic configurations must be on page before snare.js
window.io_install_stm      = false,    // do not install Active X
window.io_exclude_stm      = 12,       // do not run Active X
window.io_install_flash    = false,    // do not install Flash
window.io_enable_rip       = true;     // collect Real IP information

function io_bb_callback(blackBoxString, isComplete) {
    if ( isComplete ) {
        var element;
        if(elemnt = document.getElementById('blackBox')){
            element.value = blackBoxString;
        }
        else{
            var div = document.createElement('div');
            div.setAttribute('id', 'payment_form_gestpaypro_before');
            var input = document.createElement('input');
            input.setAttribute('type', 'hidden');
            input.setAttribute('id', 'blackBox');
            input.setAttribute('name', 'payment[blackBox]');
            input.setAttribute('value', blackBoxString);
            div.appendChild(input);
            document.getElementById('co-payment-form').appendChild(div);
        }
    }
};