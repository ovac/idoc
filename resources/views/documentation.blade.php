
<!DOCTYPE html>
<html>
  <head>
    <title>{{config('idoc.title')}}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      @import url(//fonts.googleapis.com/css?family=Roboto:400,700);

      body {
        margin: 0;
        padding: 0;
        font-family: Verdana, Geneva, sans-serif;
      }

      #redoc_container .menu-content img {
        padding: 0px 0px 30px 0px;
      }
    </style>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <link rel="apple-touch-icon-precomposed" href="/favicon.ico">
  </head>
  <body>
    <div id="redoc_container"></div>
    <script src="https://cdn.jsdelivr.net/npm/@redoc/redoc-pro@1.0.0-beta.38/dist/redocpro-standalone.min.js"></script>
    <script>
      RedocPro.init(
        "{{config('idoc.output') . "/openapi.json"}}", {
          "showConsole": true,
          "pathInMiddlePanel": true,
          "redocExport": "RedocPro",
          "layout": { "scope": "section" },
          "unstable_externalDescription": '{{route(config('idoc.external_description') ?: 'idoc.info')}}',
          "hideDownloadButton" : {{config('idoc.hide_download_button') ?: 0}}
        },
        document.getElementById("redoc_container")
      );

      var constantMock = window.fetch;
      window.fetch = function() {
        return constantMock.apply(this, arguments)
      }
    </script>
  </body>
</html>
