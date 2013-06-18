var addresses = new Array();

document.body.onload = function() {
    var val = document.getElementById('bitcoin').value;
    if (val == '') {
        val = '[address]';
    }
    document.getElementById('tweetlink').innerHTML = '<a href="https://twitter.com/intent/tweet?text=@addressmachine%20'+val+'">@addressmachine '+val+'</a>';
}

document.getElementById('bitcoin').onchange = function() {
    if ( document.getElementById('email_section').className == 'registered' ) {
        document.getElementById('email_section').className = 'unregistered';
        document.getElementById('email_submit').value = 'Link';
        document.getElementById('email_toggle').value = 'link';
    }
    update_twitter_display();
}

document.getElementById('email').onchange = function() {
    if ( document.getElementById('email_section').className == 'registered' ) {
        document.getElementById('email_section').className = 'unregistered';
        document.getElementById('email_submit').value = 'Link';
        document.getElementById('email_toggle').value = 'link';
    }
}


/*
document.getElementById('bitcoin').onkeyup = function() {
    var val = document.getElementById('bitcoin').value;
    if (val == '') {
        val = '[address]';
    }
    document.getElementById('tweetlink').innerHTML = '<a href="https://twitter.com/intent/tweet?text=@addressmachine%20'+val+'">@addressmachine '+val+'</a>';
}
*/

document.getElementById('addform').onsubmit = function() {

    var toggle = document.getElementById('email_toggle').value;

    var bitcoin = document.getElementById('bitcoin').value;
    if (bitcoin== '') {
        document.getElementById('bitcoin').focus();
        return false;
    }
    var email = document.getElementById('email').value;
    if (email == '') {
        document.getElementById('email').focus();
        return false;
    }

    var hash = hex_sha1(email.toLowerCase());

    var r = new XMLHttpRequest();
    r.open('GET', "https://lookup.addressmachine.com/addresses/email/bitcoin/user/"+hash+"/"+bitcoin, true);

    r.onreadystatechange = function () {

        if (r.readyState != 4) {
            return;
        }
        if (r.status == 200) {
            // We're supposed to be linking, but it's already linked
            if (toggle == 'link') {
                document.getElementById('email_section').className = 'registered';
                document.getElementById('email_submit').value = 'Unlink';
                document.getElementById('email_toggle').value = 'unlink';
                //alert('already registered');
                return false;
            } 
        } else {
            // We're supposed to be unlinking, but it's already unlinked
            if (toggle == 'unlink') {
                document.getElementById('email_section').className = 'unregistered';
                document.getElementById('email_submit').value = 'Link';
                document.getElementById('email_toggle').value = 'link';
                alert('not already registered, continue');
                return false;
            }
        }
        //return false;

        // OK to go ahead and submit the actual request...
        var r2 = new XMLHttpRequest();
        r2.open('POST', "request.php", true);
        r2.setRequestHeader("Content-type","application/x-www-form-urlencoded");

        r2.onreadystatechange = function () {

            if (r2.readyState != 4) {
                return;
            }
            if (r2.status == 200) {
                console.log('ok'+r2.responseText);
            } else {
                console.log('request failed');
            }
         
        }

        r2.send('email='+email+'&bitcoin='+bitcoin+'&email_toggle='+toggle);

    }

    r.send();

    return false;

}

document.getElementById('lookupform').onsubmit = function() {

    var q = document.getElementById('lookup').value;

    var isSubmittable = false;
    if (q.match(/\@/)) {
        isSubmittable = true;
    }

    if (!isSubmittable) {
        document.getElementById('lookup').focus();
        document.getElementById('lookup_results').style.display='none';
        return false;
    }

    var service;
    if (q.match(/^@/)) {
        service = 'twitter';
    } else {
        service = 'email';
    }
    var hash = hex_sha1(q.toLowerCase());
    var r = new XMLHttpRequest();
    r.open('GET', 'https://lookup.addressmachine.com/addresses/'+service+'/bitcoin/user/'+hash+'/', true);

    r.onreadystatechange = function () {

        if (r.readyState != 4) {
            return;
        }
        if (r.status == 404) {
            display_no_address(service, q);
            return false;
        } else if (r.status != 200) {
            // Currently the apache server at lookup.addressmachine.com isn't setting CORS headers for 404s.
            // This means that a response that should come back with a 404 status appear to JavaScript to have a status of 0.
            // For now, we'll treat zero status as if it's a 404, ie as if the search was fine, but showed a negative result.
            // Later, we'll fix this by either persuading apache to send us CORS headers even on 404s or by making it send us an empty list instead.
            display_no_address(service, q);
            //alert('wonky status:'+r.status);
            return false;
        }

        document.getElementById('lookup_results').innerHTML += '';

        console.log(r.responseText);
        var data = JSON.parse(r.responseText);
        if (data.length == 0) {
            display_no_address(service, q);
        }
        display_addresses(service, hash, q, data);
        

        return false;
    };

    r.send();
    return false;

}

function display_no_address(service, term) {

    document.getElementById('lookup_result_term').innerHTML = 'We couldn\'t find an address for:<br />';
    document.getElementById('lookup_result_term').appendChild(document.createTextNode(term));
    if (service == 'email') {
        document.getElementById('lookup_result_term').innerHTML += '<br />Why not <a href="mailto:'+term+'?subject=I%20Want%20To%20Send%20You%20Bitcoins&body=I%20tried%20to%20find%20a%20Bitcoin%20address%20for%20you%20at%20www.addressmachine.com%20but%20you%20were%20not%20listed.%20Why%20not%20add%20one?">ask them to add one</a>?';
    }
    if (service == 'twitter') {
        document.getElementById('lookup_result_term').innerHTML += '<br />Why not <a href="https://www.twitter.com/'+term+'">ask them to add one</a>?';
    }
    document.getElementById('lookup_result_address').innerHTML = '';
    document.getElementById('lookup_results').style.display='block';
    document.getElementById('lookup_result_signed_data_textarea').innerHTML = '';
    document.getElementById('lookup_result_signed_data').style.display='none';
    document.getElementById('lookup_result_show_hide').style.display='none';
    //////////////////document.getElementById('lookup_result_gpg_link').innerHTML = '';

}

function display_addresses(service, id, term, addresses) {

    addr = addresses[0];

    if (id == '') {
        return false;
    }
    if (addr == '') {
        return false;
    }
    console.log("need to validate address "+addr+" for id "+id);

    var r = new XMLHttpRequest();
    r.open('GET', "https://lookup.addressmachine.com/addresses/"+service+"/bitcoin/user/"+id+"/"+addr, true);
    r.onreadystatechange = function () {

        if (r.readyState != 4) {
            return;
        }
        if (r.status == 404) {
            alert('GPG-signed data not found');
            return false;
        } else if (r.status != 200) {
            alert('Error:'+r.status);
            return false;
        }
        console.log(r.responseText);
        var data = JSON.parse(r.responseText);
        for (var i=0; i<data.length; i++) {
            var item = data[i];
            if (typeof item == 'string') {
                validate_address(hash, item);
            }
        }

        console.log(data);
        var address = data.payload.address;
        // TODO: Ideally we'd verify this data...
        //var gpg_signed_data = data.gpg_signed_data;
        if (address) {
            document.getElementById('lookup_result_term').innerHTML = '';
            document.getElementById('lookup_result_term').appendChild(document.createTextNode(term));
            document.getElementById('lookup_result_address').innerHTML = '';
            document.getElementById('lookup_result_address').appendChild(document.createTextNode(address));
            document.getElementById('lookup_results').style.display='block';
            document.getElementById('lookup_result_signed_data_textarea').innerHTML = '';
            document.getElementById('lookup_result_signed_data_textarea').appendChild(document.createTextNode(data.gpg_signed_data));
            document.getElementById('lookup_result_show_hide').style.display='block';
            document.getElementById('lookup_result_gpg_link').onclick = function() {
                if (document.getElementById('lookup_result_signed_data').style.display == 'block') {
                    document.getElementById('lookup_result_signed_data').style.display='none';
                } else {
                    document.getElementById('lookup_result_signed_data').style.display='block';
                }
            }
            //document.getElementById('lookup_result_signed_data').style.display = 'block';
            //document.getElementById('lookup_results').innerHTML += '<div id="address-'+address+'" class="unverified">'+address+ '</div> ';
            //document.getElementById('lookup_results').innerHTML += '<textarea>'+gpg_signed_data+'</textarea>';
        }

        return false;

    }

    r.send();

}

function update_twitter_display() {

    var val = document.getElementById('bitcoin').value;
    if (val == '') {
        val = '[address]';
        document.getElementById('tweetlink').innerHTML = '<a href="https://twitter.com/intent/tweet?text=@addressmachine%20'+val+'">@addressmachine '+val+'</a>';
        return;
    }

}
