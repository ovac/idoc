## Info

## Error Handling

All errors follow general REST principles. Included in the body of any error response (e.g. non-200 status code) will be an error object of the form:

Parameter | Value
--------- | -------
success | The success value will be set to false
status | The HTTP error status returned
error | The detailed description of the error.
error.code | A slug/string alternative of the error code.
error.message | Message for the error.
error.fields | Available in the case of error 422.

> In the case of an error 422, a fields property will be available in the error object with the name of the affected fields as keys and an array of messages as values respectively. An example is shown below:

```json
	{
	    "status": 422,
	    "success": false,
	    "error": {
	        "code": "validation_failed",
	        "message": null,
	        "fields": {
	            "network": [
	                "The network field is required."
	            ]
	        }
	    }
	}
```

```json
	{
	    "status": 422,
	    "success": false,
	    "error": {
	        "code": "validation_failed",
	        "message": null,
	        "fields": {
	            "login": [
	                "The login field is required."
	            ]
	        }
	    }
	}
```

### This API uses the following error codes:

Error Code | Slug | Type | Meaning
---------- | ---- | ---- | -------
400 | bad_request | Bad Request | Your request sucks
401 | unauthorized | Unauthorized | Your API key is wrong or invlid
403 | forbidden | Forbidden | The data requested is hidden for real men with valid rights only
404 | not_found | Not Found | The specified resource/url-path could not be found
405 | method_not_allowed | Method Not Allowed | You tried to access a route with an invalid method
406 | not_acceptable | Not Acceptable | You requested a format that isn't available
410 | gone | Gone | The resource requested has been removed from our servers
422 | validation_failed | Unprocessible Entity | The form request is invalid.
429 | too_many_requests | Too Many Requests | You're going too fast! Slow down!
500 | server_error | Internal Server Error | We had a problem with our server. Try again later.
503 | service_unavailable | Service Unavailable | We're temporarially offline for maintanance. Please try again later.


## Pagination

> All paginated responses will append the following `pagination` section to the response like so:

```json
	{
		"status": 200,
	    "success": true,
	    "data": [
	    	{
	    		"id" : 1
	    	},
	    	{
	    		"id" : 2
	    	},
	    	{
	    		"id" : 3
	    	},
	    	e.t.c.
	    ],
		"pagination": {
	        "count": 15,
	        "total": 32,
	        "perPage": 15,
	        "currentPage": 1,
	        "totalPages": 3,
	        "links": {
	            "next": "api\/v5\/someurl?page=2"
	        }
	    }
	}
```

Certain routes, such as index listing may return an array of results.

By default, the API will return the results in batch. The `count` parameter may be used to increase the number of results per request.

To get the next batch of results, call the same route again with a `page` request parameter corresponding to the `currentPage` and 	`totalPages` property received in the last call on the `pagination` part of the response.

#### Query Parameters

Parameter | Status | Description
--------- | -------| -----------
page | optional | Used to request a particular page.
count | optional | Used to set the number of results per page
