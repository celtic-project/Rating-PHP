/**
 * This page displays a list of items for a resource link.  Students are able to rate
 * each item; staff may add, edit, re-order and delete items.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @version   4.0.0
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */

var http_request = false;
var el_id;

function getHTTPRequest() {
  http_request = false;
  if (window.XMLHttpRequest) { // Mozilla, Safari,...
    http_request = new XMLHttpRequest();
  } else if (window.ActiveXObject) { // IE
    try {
      http_request = new ActiveXObject("Msxml2.XMLHTTP");
    } catch (e) {
      try {
        http_request = new ActiveXObject("Microsoft.XMLHTTP");
      } catch (e) {
      }
    }
  }
}

function doGenerateKey() {
  getHTTPRequest()
  if (http_request) {
    var url = 'share.php?do=generate';
    var el = document.getElementById('life');
    url += '&life=' + el.value;
    el = document.getElementById('auto_approve');
    if (el.checked) {
      url += '&auto_approve=yes';
    }
    http_request.onreadystatechange = alertGenerateKey;
    http_request.open('GET', url, true);
    http_request.send(null);
  }
  return false;
}

function alertGenerateKey() {
  if (http_request.readyState == 4) {
    if (http_request.status == 200) {
      var key = http_request.responseText;
      if (key.length > 0) {
        window.prompt('Send this share key string to the other instructor:', 'share_key=' + key);
      } else {
        alert('Sorry an error occurred in generating a new share key; please try again');
      }
    } else {
      alert('Sorry unable to generate a new share key');
    }
  }
}

function doApprove(id, action, rlid) {
  getHTTPRequest()
  if (http_request) {
    var url = 'share.php?do=' + escape(action) + '&rlid=' + escape(rlid);
    el_id = id;
    http_request.onreadystatechange = alertApprove;
    http_request.open('GET', url, true);
    http_request.send(null);
  }
  return false;
}

function alertApprove() {
  if (http_request.readyState == 4) {
    if (http_request.status == 200) {
      var button = document.getElementById('btn' + el_id);
      var img = document.getElementById('img' + el_id);
      if (button.value == 'Approve') {
        button.value = 'Suspend';
        img.src = 'images/tick.gif';
      } else {
        button.value = 'Approve';
        img.src = 'images/cross.gif';
      }
    }
  }
}
