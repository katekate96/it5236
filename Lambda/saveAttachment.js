var mysql = require('./node_modules/mysql');
var config = require('./config.json');
var validator = require('./validation.js');

function formatErrorResponse(code, errs) {
	return JSON.stringify({ 
		error  : code,
		errors : errs
	});
}

exports.handler = (event, context, callback) => {
	//instruct the function to return as soon as the callback is invoked
	context.callbackWaitsForEmptyEventLoop = false;

	//validate input
	var errors = new Array();
	
	
	if(errors.length > 0) {
		// This should be a "Bad Request" error
		callback(formatErrorResponse('BAD_REQUEST', errors));
	} else {
	
	//getConnection equivalent
	var conn = mysql.createConnection({
		host 	: config.dbhost,
		user 	: config.dbuser,
		password : config.dbpassword,
		database : config.dbname
	});
	
	//prevent timeout from waiting event loop
	context.callbackWaitsForEmptyEventLoop = false;
	
	//attempts to connect to the database
		conn.connect(function(err) {
			
			if (err)  {
				// This should be a "Internal Server Error" error
				callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
			};
			console.log("Connected!");
			var sql = "INSERT INTO attachments (attachmentid, filename) VALUES (?, ?)";
			
			conn.query(sql, [event.attachmentid, event.filename], function (err, result) {
				if (err) {
					// Check for duplicate values
					if(err.errno == 1062) {
						console.log(err.sqlMessage);
						if(err.sqlMessage.indexOf('attachmentid') != -1) {
							// This should be a "Internal Server Error" error
							callback(formatErrorResponse('BAD_REQUEST', ["attachmentid already exists"]));
						} 
					} else {
						// This should be a "Internal Server Error" error
						callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
					}
	      		} else {
				        	console.log("successful new attachmentid");
			      			callback(null,"new attachmentid successful");
			      				setTimeout(function(){
			      				conn.end();
			      			}, 3000);
	      		}
		  	}); //query registration codes
		}); //connect database
	} //no validation errors
} //handler