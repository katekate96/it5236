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
	
	//prevent timeout from waiting event loop
	context.callbackWaitsForEmptyEventLoop = false;

	//validate input
	var errors = new Array();
	
	 // Validate the user input
	validator.validateUserSession(event.usersessionid, errors);
	
	
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
	

	//attempts to connect to the database
	conn.connect(function(err) {
	  	
		if (err)  {
			// This should be a "Internal Server Error" error
			callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
		};
		console.log("Connected!");
		var sql = "DELETE FROM usersessions WHERE usersessionid = ? OR expires < now()";
		
		conn.query(sql, [event.usersessionid], function (err, result) {
		  	if (err) {
				// This should be a "Internal Server Error" error
				callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
		  	} else {
		  		console.log("session deleted");
		  		callback(null, "sessiondeleted");
		  		//successful logout
				} //good code count
		  	}); //query logout codes
		}); //connect database
	} //no validation errors
}; //handler
	