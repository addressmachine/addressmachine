var addresses = new Array();
var is_add_in_flight = false;
var is_lookup_in_flight = false;

document.body.onload = function() {
    // The browser may have kep the content from a previous form entry.
    // Otherwise it will be empty.

    // This should default to link, even if the browser saved "unlink" from a refresh
    document.getElementById('email_toggle').value = 'link';

    // These should call our form change handler on copy-paste events. 
    // Browser support may be a bit patchy.
    //document.getElementById('bitcoin').addEventListener ("DOMCharacterDataModified", handle_add_form_change, false);
    //document.getElementById('email').addEventListener ("DOMCharacterDataModified", handle_add_form_change, false);


    handle_add_form_change();
}

function handle_add_form_change() {

    var can_submit = true;

    // If a previous address/email pair turned out to be registered already
    // ...we will have set the "registered" flags on the form.
    // Return these to their default 'unregistered' state when the form changes.
    if ( document.getElementById('email_section').className == 'registered' ) {
        document.getElementById('email_section').className = 'unregistered';
        document.getElementById('email_submit').value = 'Link';
        document.getElementById('email_toggle').value = 'link';
    }

    // We can't do anything else useful until we have a bitcoin address.
    var val = document.getElementById('bitcoin').value;
    if (val == '') {
        document.getElementById('bitcoin').focus();
        can_submit = false;
    }

    update_twitter_display();

    // Slightly eccentrically, we'll enable the submit button once the bitcoin addresses is filled.
    // It won't do anything until you fill the email field too...
    // ...and if you click it too soon, it will just throw the focus back to the email field.
    // We do it this way because we can't reliably get form change events when the email is filled in
    // ...through copy-pasting or choosing from a previously-typed list.
    document.getElementById('email_submit').disabled = !can_submit;

    if (document.getElementById('email').value == '') {
        // Focus on email if the previous field was ok
        if (can_submit) {
            document.getElementById('email').focus();
        }
        can_submit = false;
    }


}

document.getElementById('bitcoin').onchange = function() {

    handle_add_form_change();

}

document.getElementById('email').onchange = function() {

    handle_add_form_change();

}

document.getElementById('email').onkeyup = function() {

    handle_add_form_change('email');

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

    // If either of the fields is empty, throw the focus onto the missing element and return.
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

    // Check if it's already in our list of things we've started.
    var link_change_item_id = toggle + '-' + bitcoin + '-' + email;
    if (document.getElementById(link_change_item_id)) {
        var original_class = document.getElementById(link_change_item_id).className;
        document.getElementById(link_change_item_id).className = original_class + ' highlighted'; 
        setTimeout(function() {
            document.getElementById(link_change_item_id).className = original_class;
        }, 3000);
        return false;
    }

    // Disable the submit button so the user can't submit twice.
    document.getElementById('email_submit').disabled = true;

    // TODO: Add a progress indicator (although this shouldn't take long)

    var hash = hex_sha1(email.toLowerCase());

    var r = new XMLHttpRequest();
    var lookup_url = "https://lookup.addressmachine.com/addresses/email/bitcoin/user/"+hash+"/"+bitcoin;
    r.open('GET', lookup_url, true);

    r.onreadystatechange = function () {

        if (r.readyState != 4) {
            return;
        }

        // Check for an attempt to link something that is already linked
        // ...or unlink something that wasn't linked in the first place.
        if (r.status == 200) {
            // We're supposed to be linking, but it's already linked
            if (toggle == 'link') {
                document.getElementById('email_section').className = 'registered';
                document.getElementById('email_submit').value = 'Unlink';
                document.getElementById('email_toggle').value = 'unlink';
                document.getElementById('email_submit').disabled = false;
                //alert('already registered');
                return false;
            } 
        } else {
            // We're supposed to be unlinking, but it's already unlinked
            if (toggle == 'unlink') {
                document.getElementById('email_section').className = 'unregistered';
                document.getElementById('email_submit').value = 'Link';
                document.getElementById('email_toggle').value = 'link';
                document.getElementById('email_submit').disabled = false;
                //alert('not already registered, continue');
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

                console.log(r2.responseText);

                // clone the template
                var link_change_item = document.createElement('div');
                link_change_item.className = 'link_change_item';
                link_change_item.id = toggle + '-' + bitcoin + '-' + email;
                link_change_item.innerHTML = document.getElementById('link_change_item_template').innerHTML;

                // Set each element that we may need to display to the user
                // And set a machine-readable data- attribute for each that will be used when polling for changes.
                //link_change_item.getElementsByClassName('link_change_type')[0].innerHTML = toggle;
                link_change_item.setAttribute('data-link-change-type', toggle);
                link_change_item.getElementsByClassName('link_change_email')[0].innerHTML = email;
                link_change_item.setAttribute('data-link-change-email', email);
                link_change_item.getElementsByClassName('link_change_bitcoin')[0].innerHTML = bitcoin;
                link_change_item.setAttribute('data-link-change-bitcoin', bitcoin);
                //link_change_item.getElementsByClassName('link_change_status')[0].innerHTML = 'Check email';
                link_change_item.setAttribute('data-link-change-status', 'check');

                console.log(link_change_item);
                document.getElementById('link_change_queue').appendChild(link_change_item);
                document.getElementById('link_change_queue').style.display = 'block';

                // Start polling for when they complete this process.
                poll_for_status_change(toggle, bitcoin, email);


            } else {

                console.log('request failed');

            }

            // If this worked, we're now waiting for the user to deal with their email.
            // They can do address/email pairs in the meantime if they want to.
            document.getElementById('email_submit').disabled = false;


            //document.getElementById('email_submit').disabled = false;
            //document.getElementById('email_submit').backgroundColor = '#ff0000';
         
        }

        r2.send('email='+email+'&bitcoin='+bitcoin+'&email_toggle='+toggle);

    }

    r.send();

    return false;

}

function poll_for_status_change(toggle, bitcoin, email) {

    // There should be a div in the document like this.
    var link_change_item_id = toggle + '-' + bitcoin + '-' + email;

    // If the div has gone there's no point in polling for it any more.
    if (!document.getElementById(link_change_item_id)) {
        return;
    }

    setTimeout(function () {

        var hash = hex_sha1(email.toLowerCase());
        var lookup_url = "https://lookup.addressmachine.com/addresses/email/bitcoin/user/"+hash+"/"+bitcoin;

        var r = new XMLHttpRequest();
        r.open('GET', lookup_url, true);

        r.onreadystatechange = function () {

            if (r.readyState != 4) {
                return;
            }

            if (r.status == 200) {

                console.log('done');

                var link_change_item = document.getElementById(link_change_item_id);
                link_change_item_id.className = 'link_done_item';
                link_change_item.getElementsByClassName('link_change_status')[0].innerHTML = link_change_item.getElementsByClassName('link_change_status')[0].getAttribute('data-html-on-completion');
                link_change_item.setAttribute('data-link-change-status', 'done');

                

                return;

            } 

            // Not there yet, poll again. 
            poll_for_status_change(toggle, bitcoin, email);
            return false;

        }

        r.send();

    }, 3000);

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

    document.getElementById('lookup_button').disabled = true;

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
        
        window.setTimeout( function() {
            document.getElementById('lookup_button').disabled = false;
        }, 500);

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
    document.getElementById('tweetlink').innerHTML = '<a href="https://twitter.com/intent/tweet?text=@addressmachine%20'+val+'">@addressmachine '+val+'</a>';
    return;

}
