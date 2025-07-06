function flash(message = "", color = "info") {
    let flash = document.getElementById("flash");
    //create a div (or whatever wrapper we want)
    let outerDiv = document.createElement("div");
    outerDiv.className = "row justify-content-center";
    let innerDiv = document.createElement("div");

    //apply the CSS (these are bootstrap classes which we'll learn later)
    innerDiv.className = `alert alert-${color}`;
    //set the content
    innerDiv.innerText = '[js]: ' + message;

    outerDiv.appendChild(innerDiv);
    //add the element to the DOM (if we don't it merely exists in memory)
    flash.appendChild(outerDiv);
}
function isValidPassword(pass) {
    return pass?.length >= 8;
}
function isValidEmail(pass) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(pass);
}
function isValidUsername(pass) {
    return /^[a-z0-9_-]{3,30}$/.test(pass);
}
function isValidConfirm(origPass, newPass) {
    return origPass == newPass;
    
}
function empty (pass) {
    return pass?.length == 0;
}