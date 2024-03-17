// autoplayonhover.js

var videoList = document.getElementsByTagName("video");
console.log(videoList)


var elements = document.getElementsByClassName('embed-responsive-item');
// var revElements = Array.from(document.getElementsByClassName('embed-responsive-item')).reverse();

(function () {
    for (let element of elements) {
//        console.log ("\n\r>>>>>>>>>>><< element.id", element.id);
        console.log ("\n\r>>>>>>>>>>><< element.muted", element.muted);
        console.log ("\n\r>>>>>>>>>>><< element.currentTime", element.currentTime);
//        console.log ("\n\r>>>>>>>>>>><< element.paused", element.paused);
//        console.log ("\n\r>>>>>>>>>>><< element.poster", element.poster);


        // console.log ("\n\r>>>>>>>>>>><< element.onmouseover", element.onmouseover);
        // console.log ("\n\r>>>>>>>>>>><< element.onmouseoutr", element.onmouseout);

        // element.onmouseover
        // element.onmouseot = "this.pause();"

        element.setAttribute('onmouseover', " this.play(); \n this.setAttribute('controls','controls'); ");
//        element.setAttribute('onmouseout', " this.pause();  \n this.setAttribute('controls','');  ");

        if (!element.muted) {
            element.setAttribute('onmouseover', " this.play(); " +
                "\n this.setAttribute('controls','controls'); " +
                "\n this.setAttribute('muted',''); " +
                "");
            element.setAttribute('onmouseout', " this.pause();  " +
                "\n this.setAttribute('controls','');  " +
                "\n this.setAttribute('muted','muted'); " +
                "");

        }


        // console.log("element", element.id)
        // renderProgress(element);
    } // for in list
})();

