$(function () {
var books;
var currentlyDisplayedBooks = [];
$("select[name='personId']").selectize({
    create: false
});
$("textarea[tabindex='1']").focus();
$.getJSON("/libraries/3/book-list-json", function(result, status){
    if (status === 'success' && result.hasOwnProperty('books') && result.books !== null && typeof result.books === 'object') {
      books = result.books;
    }
});

$("textarea[name='bookIds']").bind('input propertychange', function() {

  var text = $("textarea[name='bookIds']").val();
  var regex = /\d{5}/gm;
  var barcodes = [];
  var res;
  do {
    res = regex.exec(text);
    if (res) {
      barcodes.push(res[0]);
    }
  } while (res);
  if (!equalArrays(barcodes, currentlyDisplayedBooks)) {
    var $bookList = $("#book-list");
    var html = "";
    for (var i = 0, l=barcodes.length; i<l; i++) {
      if (!books.hasOwnProperty(barcodes[i])) {
        html += "<li class=\"text-danger\">"+barcodes[i]+": Not a valid book</li>";
      } else if (books[barcodes[i]].hasOwnProperty('title')) {
        if (true === books[barcodes[i]]['isCheckedOut']) {
          html += "<li class=\"text-warning\">"+barcodes[i]+": "+books[barcodes[i]]['title']+", Checked out by "+books[barcodes[i]]['checkedOutBy']+"</li>";
        } else {
          html += "<li class=\"text-success\">"+barcodes[i]+": "+books[barcodes[i]]['title']+"</li>";
        }
      }
    }
    $bookList.html(html);
    currentlyDisplayedBooks = barcodes;
  }
});

var equalArrays = function (array1, array2) {
    // if the other array is a falsy value, return
    if (!array2)
        return false;
    // compare lengths - can save a lot of time
    if (array1.length != array2.length)
        return false;

    for (var i = 0, l=array1.length; i < l; i++) {
        // Check if we have nested arrays
        if (array1[i] instanceof Array && array2[i] instanceof Array) {
            // recurse into the nested arrays
            if (!array1[i].equals(array2[i]))
                return false;
        }
        else if (array1[i] != array2[i]) {
            // Warning - two different object instances will never be equal: {x:20} != {x:20}
            return false;
        }
    }
    return true;
}
});