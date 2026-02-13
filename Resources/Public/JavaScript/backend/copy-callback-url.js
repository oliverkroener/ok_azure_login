/**
 * Copy backend callback URL to OS clipboard.
 */
const btn = document.getElementById('copyCallbackUrl');
if (btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const url = document.getElementById('backendCallbackUrl').value;
        const origHtml = btn.innerHTML;
        const textarea = document.createElement('textarea');
        textarea.value = url;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        btn.innerHTML = '<span style="font-size:14px">&#10003;</span>';
        setTimeout(function() { btn.innerHTML = origHtml; }, 1500);
    });
}
