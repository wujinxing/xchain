(function() {
  window.Streamer = (function($) {
    var exports;
    exports = {};
    exports.init = function(pusherUrl) {
      var client, txList;
      client = new window.Faye.Client("" + pusherUrl + "/public");
      txList = $('.transactionList');
      client.subscribe('/tick', function(message) {
        console.log('ts=', message.ts);
      });
      client.subscribe('/tx', function(event) {
        var desc, newEl;
        if (event.isCounterpartyTx && event.type !== 'send') {
          desc = "" + event.type;
        } else {
          desc = "" + event.quantity + " " + event.asset;
        }
        newEl = $("<div class=\"row " + (event.isCounterpartyTx ? 'xcp' : 'btc') + "-tx\">\n    <span class=\"highlight\"></span>\n\n    <div class=\"medium-9 columns txid\">\n        <a href=\"https://blockchain.info/tx/" + event.txid + "\">" + event.txid + "</a>\n    </div>\n    <div class=\"medium-3 columns amount\">\n        " + event.quantity + " " + event.asset + "\n    </div>\n</div>   \n");
        newEl.hide().prependTo(txList).slideDown();
        $('div.row', txList).slice(24).remove();
      });
    };
    return exports;
  })(jQuery);

}).call(this);
