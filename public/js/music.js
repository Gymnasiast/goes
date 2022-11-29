'use strict';

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('#music-form').addEventListener('submit', function (event)
    {
        event.preventDefault();
        send(this.getAttribute('action'), this.getAttribute('method'), new FormData(this)).then();
    });

    document.getElementById('showAdvanced').checked = false;
    document.querySelector('#showAdvanced').addEventListener('click', function (event)
    {
         if (document.getElementById('showAdvanced').checked)
             document.querySelector('#advanced-options').setAttribute('class', '');
         else
             document.querySelector('#advanced-options').setAttribute('class', 'd-none');
    });
});
