function renderProgress (element) {

//            this.element = document.getElementById(this.jsonId),
    this.element = element,
        latestProgress = 0.0,
        remaining = 0,
        remainingTime = 0,
        lastUpdate = 0,

        updateProperties = function (o) {

            processCounterTotal++;
            console.log("processCounterTotal", processCounterTotal)
/*
            if (this.element.id == "tx_videoprocessing_progress_1") {
                processCounter[1] = processCounter[1] + 1;
                console.log("processCounter1", processCounter[1])
            }

            if (this.element.id == "tx_videoprocessing_progress_2") {
                processCounter[2] = processCounter[2] + 1;
                console.log("processCounter2", processCounter[2])
            }
*/
            this.o = o
            console.log("[updateProperties]", this.element.id, this.o);

            uid = String(o.uid);
            // console.log("!!! this.uid:", this.uid, element.id);
            latestProgress = Number(o.progress);
            // console.log("latestProgress", this.uid, this.latestProgress, jsonId);
            remainingTime = Number(o.remaining) || Infinity;
            // console.log("this.remaining time" , remainingTime);
            lastUpdate = Number(o.lastUpdate);
            lastStatus = String(o.status, this.jsonId);

            processingDuration = Number(o.processingDuration);
            // console.log("processingDuration ", this.uid, processingDuration.toFixed(1) );

            progressStepsNum = String(o.progressSteps.length);
            // console.log("progressStepsNum", progressStepsNum);

            // progressSteps = String(o.progressSteps);
            // console.log("progressSteps ", this.uid, progressSteps);

        },

        this.lastContent = this.element.textContent,
        updateTimeout = 1000,
        arrayUids = this.element.dataset.uids.split("-");

        requestProperties = function (callback) {
            console.log("[videoprogress.js] requestProperties() this.element.id", this.element.id)
            console.log("this.updateTimeout", updateTimeout);

            clearTimeout(updateTimeout);

            if (arrayUids) {
                // console.log("this.element.dataset.uids", this.element.dataset.uids)
                // const arrayUids = this.element.dataset.uids.split("-");
                // console.log("arrayUids", arrayUids);
                for (let index = 0; index < arrayUids.length; ++index) {
                    const uid = arrayUids[index];
                    // ...use `element`...
                   //  console.log("uid", uid)

                    xhr = new XMLHttpRequest();
                    xhr.onload = function () {
                        this.response = xhr.responseText;
                        // console.log("this.element.id", this.element.id);
                        // console.log("[videoprogress.js] this.response", element.id, this.response);
                        updateProperties(JSON.parse(this.response));
                        updateTimeout = setTimeout(
                            requestProperties,
                            pollingInterval
                        );
                        callback && callback();
                    };

                    // this.xhr.open('GET', this.element.dataset.updateUrl, true);
                    this.xhr.open('GET', "https://t3v11.kukurtihar.com/index.php?eID=tx_video_progress&uids%5B0%5D="+uid , true);
                    this.xhr.send(null);


                }

            }

            //
            // https://t3v11.kukurtihar.com/index.php?eID=tx_video_progress&uids%5B0%5D=1007
            // https://t3v11.kukurtihar.com/index.php?eID=tx_video_progress&uids[0]=1007


        },
        render = function () {
            // this.uid = uid;
            // check if the target node is still within the document and stop everything if not


            if (document.getElementById(element.id) !== this.element) {
               clearTimeout(updateTimeout);
               console.log("not in focus?")
               return;
             }

            // calculate the progress until it should be finished
            // console.log("maxPredictedProgress "+uid+"", $maxPredictedProgress);

            maxPredictedProgress = 2000;

            progress = Math.min(1.0, Math.min(maxPredictedProgress, Date.now() - lastUpdate) / remainingTime);
            // console.log("Date.now() - lastUpdate", Date.now() - lastUpdate);
            // console.log("Date.now() - lastUpdate) / remainingTime", (Date.now() - lastUpdate) / remainingTime)
            // console.log("progress:", progress, progress.toFixed(1));

            this.progressPercent = ((latestProgress + (1.0 - latestProgress) * progress) * 100).toFixed(1) + '%';
            this.progressInt = parseInt( ((latestProgress + (1.0 - latestProgress) * progress) * 100).toFixed(1) );

            // console.log("progressPercent", progressPercent, progressInt)

            if (progress > 0.01 ) {
                newContent = this.progressPercent + ' duration:' + processingDuration.toFixed(1) + "s, steps:" + progressStepsNum ;
            } else {
                newContent = this.progressPercent;
            }

            // console.log("[videoprogress.js] render: uid " + uid + ": ", progress.toFixed(2), element.id);
            // progressInt = ((latestProgress + (1.0 - latestProgress) * progress) * 100).toFixed(1);
            // console.log("[render]", this.element.id, uid, progress.toFixed(0), newContent);

            // this.element.style.width = newContent;
            // this.element.style.background = 'hsl('+progressInt+' 100% 50%)'

            if (lastContent !== newContent) {
                this.element.dataset.progress = this.progressPercent;
                this.element.dataset.uid = this.uid;
                this.element.dataset.status = this.lastStatus;
                this.element.dataset.processingDuration = this.processingDuration;

                this.element.style.width = this.progressPercent;
//                this.element.textContent = this.progressPercent;
                this.element.style.background = 'hsl(' + this.progressInt + ' 100% 50%)'


                if (progress > 0.3 ) {
                    this.element.style.color = '#fff'
                }

                if (progress > 0.7 ) {
                    this.element.style.color = '#000'
                }

                lastContent = this.progressPercent;
            } else {
                console.log("no change")
            }

            if (this.progressInt == 0) {
                this.element.textContent = '[' + uid + '] fresh: ' + newContent;
            } else if ( this.progressInt < 100) {
                this.element.textContent = '[' + uid + '] running: ' + newContent;
            } else if ( this.progressInt == 100) {
                this.element.textContent = '[' + uid + '] finished: ' + newContent;
                this.element.dataset.status = 'finished'
            }
/*
            if (progressInt > 0) {
                console.log("!!!! processing!")
            }
 */

            if (this.progressInt < 100) {
                milliseconds = remainingTime / (1.0 - latestProgress) / 1000;

                setTimeout(render, Math.max(2000, Math.min(4500, milliseconds)));

            } else {
//                console.log(">>>>>>>>>>>>>>>>> finished? clear updateTimeout:", updateTimeout);
                clearTimeout(updateTimeout);

//                console.log("lastUpdate + 500", lastUpdate + 500);

                if (document.hasFocus() && lastUpdate + 500 > Date.now()) {
//                    console.log("setTimeout !!!!");
                    setTimeout(function () {
                        if (!window.video_is_reloading) {
                            location.reload();
                            window.video_is_reloading = true;
                        }
                    }, 1500);
                    // }.bind(2500));
                }
            }
//            console.log("end render")

        } // render()
    ;
    requestProperties(render);
    // })(element);
};


(function () {


    // this.jsonId = jsonId;
    // console.log("this.jsonId, jsonId:", this.jsonId, jsonId);
    // console.log(">>>>>000 jsonId", jsonId, $jsonId, this.jsonId);


    var elements = document.getElementsByClassName('tx_videoprocessing_progress');
    var revElements = Array.from(document.getElementsByClassName('tx_videoprocessing_progress')).reverse();

//    var bar = new Promise((resolve, reject) => {
//
//        foo.forEach((value, index, list) => {
//            console.log(value);
//            if (index === array.length -1) resolve();
//        });
//
//    });
//
//    bar.then(() => {
//        console.log('All done!');
//    });


    for (let element of revElements) {
        console.log ("\n\r>>>>>>>>>>><< element.id", element.id);
        // this.jsonId = element.id;
        pollingInterval = 3000,
        processCounterTotal = 0,
        processCounter = [],
        processCounter[0] = 0,
        processCounter[1] = 0,
        processCounter[2] = 0;


//        console.log("element", element.id)
        // renderProgress(element);

//        renderProgress(element);

    } // for in list


})();

