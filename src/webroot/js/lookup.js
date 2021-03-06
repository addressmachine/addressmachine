var addresses = new Array();

document.getElementById('lookupform').onsubmit = function() {

    var q = document.getElementById('lookup').value;

    var isSubmittable = false;
    if (q.match(/\@/)) {
        isSubmittable = true;
    }

    if (!isSubmittable) {
    console.log('not submitted');
        document.getElementById('lookup').focus();
        document.getElementById('lookup_results').style.display='none';
        return false;
    }

    //document.getElementById('lookup_button').disabled = true;

    var service;
    if (q.match(/^@/)) {
        service = 'twitter';
    } else {
        service = 'email';
    }
    var hash = hex_sha1(q.toLowerCase());

    var r = createCORSRequest('GET', 'https://lookup.addressmachine.com/addresses/'+service+'/bitcoin/user/'+hash+'/'); 
    if (!r) {
        alert('cors request failed');
    }
    r.onload = function () {

        document.getElementById('lookup_results').innerHTML += '';

        console.log(r.responseText);
        var data = JSON.parse(r.responseText);
        if (!data.active || data.active.length == 0) {
            display_no_address(service, q);
            return false;
        }

        display_addresses(service, hash, q, data.active);
        
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

    var r = createCORSRequest('GET', "https://lookup.addressmachine.com/addresses/"+service+"/bitcoin/user/"+id+"/"+addr);
    r.onload = function () {
        
        console.log(r.responseText);
        var data = JSON.parse(r.responseText);

        if (!data.payload) {
            console.log("need to validate address "+addr+" for id "+id);
            alert('Error: Data no longer there.');
            return;
        }
        /*
        for (var i=0; i<data.length; i++) {
            var item = data[i];
            if (typeof item == 'string') {
                validate_address(hash, item);
            }
        }
        */

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
