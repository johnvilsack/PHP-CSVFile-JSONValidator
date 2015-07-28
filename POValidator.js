$("form").submit(function (e) { e.preventDefault(); });

// Drag and Drop: http://jsfiddle.net/mhwzrLbc/
var lastTarget = null;

function isFile(evt) {
	var dt = evt.dataTransfer;

	for (var i = 0; i < dt.types.length; i++) {
			if (dt.types[i] === "Files") {
					return true;
			}
	}
	return false;
}

window.addEventListener("dragenter", function (e) {
	if (isFile(e)) {
		lastTarget = e.target;
		document.querySelector("#dropzone").style.visibility = "";
		document.querySelector("#dropzone").style.opacity = 1;
		document.querySelector("#textnode").style.fontSize = "48px";
	}
});

window.addEventListener("dragleave", function (e) {
	e.preventDefault();
	if (e.target === lastTarget) {
		document.querySelector("#dropzone").style.visibility = "hidden";
		document.querySelector("#dropzone").style.opacity = 0;
		document.querySelector("#textnode").style.fontSize = "42px";
	}
});

window.addEventListener("dragover", function (e) {
	e.preventDefault();
});

window.addEventListener("drop", function (e) {
	e.preventDefault();
	document.querySelector("#dropzone").style.visibility = "hidden";
	document.querySelector("#dropzone").style.opacity = 0;
	document.querySelector("#textnode").style.fontSize = "42px";
	if(e.dataTransfer.files.length == 1)
	{
		$('#prompt').html("<h2>" + e.dataTransfer.files[0].name + "</h2>");
		$('#prompt').addClass("center");
		$('#prompt').css("font-size:48px;font-weight:bold;");
		$('input[type=file]').prop('files', e.dataTransfer.files);
		uploadFiles();
	}
});
// END FULL PAGE DRAG AND DROP


// Catch the form submit and upload the files
function uploadFiles(event)
{
	// Set multiple times, so var
	var location = '#payload';

	// Dump existing payload if one exists
	$("#log").empty();

	// Get current file
	var uploadData = $(location).prop("files")[0];

	var FileData = new FormData();
	$.each($(location).prop("files"), function(i, file) {
		FileData.append('payload-'+i, file);
	});

	// Send JSON request
	$.ajax({
		url: '/po/parse/',
		type: 'POST',
		data: FileData,
		cache: false,
		// dataType: 'json',
		processData: false,
		contentType: false,
		success: function(data, textStatus, jqXHR) {
			console.log(data);
			if (data.Error != undefined) {
				$.RESULTS = new Object;
				$.RESULTS = data;
				getErrors(data);
				outputLog();
			} else {
				alert("Something went terribly wrong.   Check your file and try again.")
			}
		},
		error: function(jqXHR, textStatus, errorThrown) {
			console.log('ERRORS: ' + textStatus);
		}
	});
}

// Display good or bad DIV depending on the outcome.
function getErrors(data) {
	if (data.Error == "1") {
		getErrorLog();
		$('#beforeCard').hide();
		$('#errorsFound').fadeIn();
		$('#validPO').hide();

	} else {
		// Show downloads
		$('#beforeCard').hide();
		$('#errorsFound').hide();
		$('#validPO').fadeIn();
		getFile();
	}
}

function getFile() {
	$('#btn-all').click(function(){
		JSONToCSVConvertor($.RESULTS.Data, "VALID-POIMPORT-", true);
	});

	$('#btn-vendor').click(function(){
		JSONToCSVConvertor($.RESULTS.VendorPO, "VALID-VENDOR-", true);
	});
}

function getErrorLog() {
	$('#btn-error').click(function(){
		JSONToCSVConvertor($.RESULTS.LogFile, "ERRORS-", true);
	});
}

function outputLog() {
	$.each($.RESULTS.Log, function(type, list) {
		if (type == "Brand") {
			var errortype = "panel-warning";
			type = "New Brands Detected!";
		} else {
			var errortype = "panel-danger";
		}
		var ErrorBlock = "<div class=\"col s6\"><div class=\"panel "+errortype+"\">";
		ErrorBlock += "<div class=\"panel-heading\">" + type + "</div>";
		ErrorBlock += "<ul class=\"list-group\">";
		$.each(list, function (itemid, value) {
			ErrorBlock += "<li class=\"list-group-item\">" + itemid + " <span class=\"pull-right\">" + value + "</span></li>";
		});
		ErrorBlock += "</ul></div></div>";

		$(ErrorBlock).appendTo($("#log"));
	});
}

// JSON to CSV converter!
// Found at: http://jsfiddle.net/hybrid13i/JXrwM/
function JSONToCSVConvertor(JSONData, ReportTitle, ShowLabel) {
	//If JSONData is not an object then JSON.parse will parse the JSON string in an Object
	var arrData = typeof JSONData != 'object' ? JSON.parse(JSONData) : JSONData;
	var CSV = '';
	//This condition will generate the Label/Header
	if (ShowLabel) {
			var row = "";

			//This loop will extract the label from 1st index of on array
			for (var index in arrData[0]) {
					//Now convert each value to string and comma-seprated
					row += index + ',';
			}

			row = row.slice(0, -1);

			//append Label row with line break
			CSV += row + '\r\n';
	}

	//1st loop is to extract each row
	for (var i = 0; i < arrData.length; i++) {
			var row = "";
			
			//2nd loop will extract each column and convert it in string comma-seprated
			for (var index in arrData[i]) {
					row += '"' + arrData[i][index] + '",';
			}

			row.slice(0, row.length - 1);

			CSV += row + '\r\n';
	}

	if (CSV == '') {
		alert("Invalid data");
		return;
	}   


	var fileName = ReportTitle + $.RESULTS.name;

	//Initialize file format you want csv or xls
	var uri = 'data:text/csv;charset=utf-8,' + escape(CSV); 

	//this trick will generate a temp <a /> tag
	var link = document.createElement("a");    
	link.href = uri;

	//set the visibility hidden so it will not effect on your web-layout
	link.style = "visibility:hidden";
	link.download = fileName;

	//this part will append the anchor tag and remove it after automatic click
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
}
