document.body.onload = function() {
    // The browser may have kep the content from a previous form entry.
    // Otherwise it will be empty.

    // This should default to link, even if the browser saved "unlink" from a refresh
    document.getElementById('email_toggle').value = 'link';

    // These should call our form change handler on copy-paste events. 
    // Browser support may be a bit patchy.
    //document.getElementById('bitcoin').addEventListener ("DOMCharacterDataModified", handle_add_form_change, false);
    //document.getElementById('email').addEventListener ("DOMCharacterDataModified", handle_add_form_change, false);


    handle_add_form_change('body');
}

function handle_add_form_change(changed_entry) {

    var can_submit = true;

    // If a previous address/email pair turned out to be registered already
    // ...we will have set the "registered" flags on the form.
    // Return these to their default 'unregistered' state when the form changes.
    if ( (changed_entry == 'email') || ( changed_entry == 'bitcoin') ) {
        if ( document.getElementById('email_section').className == 'registered' ) {
            document.getElementById('email_section').className = 'unregistered';
            document.getElementById('email_submit').value = 'Link';
            document.getElementById('email_toggle').value = 'link';
        }
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

    handle_add_form_change('bitcoin');

}

document.getElementById('email').onchange = function() {

    handle_add_form_change('email');

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
    console.log('got toggle value '+toggle);
    console.log(document.getElementById('email_toggle'));

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
    var r = createCORSRequest('GET', lookup_url);

    r.onload = function () {

        var data = JSON.parse(r.responseText);
        var found = (data && data.gpg_signed_data);

        console.log('Response was '+r.responseText);

        // Check for an attempt to link something that is already linked
        // ...or unlink something that wasn't linked in the first place.
        if (found) {
            // We're supposed to be linking, but it's already linked
            if (toggle == 'link') {
                document.getElementById('email_section').className = 'registered';
                document.getElementById('email_submit').value = 'Unlink';
                document.getElementById('email_toggle').value = 'unlink';
                document.getElementById('email_submit').disabled = false;
                document.getElementById('email').focus();
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
                document.getElementById('email').focus();
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

        var r = createCORSRequest('GET', lookup_url);

        r.onload = function () {

            var data = JSON.parse(r.responseText);
            var found = (data && data.gpg_signed_data);

            if ( ( ( toggle == 'link' ) && ( found ) ) || ( ( toggle == 'unlink' ) && ( !found ) ) ) {

                console.log('done');

                var link_change_item = document.getElementById(link_change_item_id);
                link_change_item_id.className = 'link_done_item';
                link_change_item.getElementsByClassName('link_change_status')[0].innerHTML = link_change_item.getElementsByClassName('link_change_status')[0].getAttribute('data-html-on-'+toggle+'-completion');
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
