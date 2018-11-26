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
	
	 // Validate the user input
	validator.validateName(event.name, errors);
	validator.validateExtension(event.extension, errors);
	
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
			var sql = "INSERT INTO attachmenttypes (attachmenttypeid, name, extension) VALUES (?, ?, ?)";
			
			conn.query(sql, [event.attachmenttypeid, event.name, event.extension], function (err, result) {
				if (err) {
					// Check for duplicate values
					if(err.errno == 1062) {
						console.log(err.sqlMessage);
						if(err.sqlMessage.indexOf('attachmenttypeid') != -1) {
							// This should be a "Internal Server Error" error
							callback(formatErrorResponse('BAD_REQUEST', ["Attachement Type ID already exists"]));
						} else if(err.sqlMessage.indexOf('name') != -1) {
							// This should be a "Internal Server Error" error
							callback(formatErrorResponse('BAD_REQUEST', ["Name is already registered"]));
						} else {
							// This should be a "Internal Server Error" error
							callback(formatErrorResponse('BAD_REQUEST', ["Duplicate value"]));
						}
					} else {
						// This should be a "Internal Server Error" error
						callback(formatErrorResponse('INTERNAL_SERVER_ERROR', [err]));
					}
	      		} else {
				        	console.log("successful new attachmenttype");
			      			callback(null,"new attachmenttype successful");
	      		}
		  	}); //query registration codes
		}); //connect database
	} //no validation errors
} //handler