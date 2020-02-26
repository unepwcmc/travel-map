var email = "";

function init() {
  showLogin();

  $('input[id="unep-check"]').change(function(){
    if($(this).is(':checked')) {
      $("#org-admin").attr("placeholder", "Project Name");
    } else {
      $("#org-admin").attr("placeholder", "Organisation");
    }
  });

  document.getElementById("start-date-map").valueAsDate = new Date();
  var date = new Date();
  date.setMonth(1 + date.getMonth());
  document.getElementById("end-date-map").valueAsDate = date;

  var tabs = ["travel", "wish", "admin"];
  tabs.forEach(e => {$("#warning-"+e).hide();});

  openTab("cal");
}

function openTab(type) {
  $(".tabcontent").hide();
  $(".tab button").css("background-color", "");
  $("#"+type+"-btn").css("background-color", "#f4f4f4");
  $("#"+type).show();
}

function showLogin() {
  var div1 = document.createElement("div");
  div1.setAttribute("class", "modal");
  div1.setAttribute("id", "login");

  var div2  = document.createElement("div");
  div2.setAttribute("class", "modal-dialog");

  var divContent = document.createElement("div");
  divContent.setAttribute("class", "modal-content");

  var divBody = document.createElement("div");
  divBody.setAttribute("class", "modal-body");

  var warning = document.createElement("div");
  warning.setAttribute("class", "alert alert-danger");
  warning.setAttribute("id", "warning");
  warning.innerText = "Please enter a valid email address.";

  var input = document.createElement("input");
  input.setAttribute("class", "form-control");
  input.setAttribute("id", "email");
  input.setAttribute("placeholder", "Email");
  input.setAttribute("value", "");

  var button = document.createElement("button");
  button.setAttribute("class", "btn btn-primary");
  button.setAttribute("onclick", "tryLogin()");
  button.innerText = "Login";

  divBody.append(warning);
  divBody.append(input);
  divBody.append(button);

  divContent.append(divBody);

  div2.append(divContent);
  div1.append(div2);

  $("body").append(div1);
  $("#warning").hide();
  $("#login").show();
}

function tryLogin() {
  if ($("#email").val()=="") {
    //TODO better email validation
    $("#warning").show();
  } else {
    email = $("#email").val();
    $("#login").remove();

    getAllTravelFromUser(email, makeDefaultTravel);
    $("#travel-add").hide();
    $("#travel-default").show();

    getAllWishesFromUser(email, makeWishes);
    $("#match-previews").hide();
    $("#matches-back-btn").hide();
    $("#wish-previews").show();

    initMap();
  }
}

function makeWishes(wishes) {
  console.log("Wishes: \n"+ JSON.stringify(wishes));
  if (wishes.length>0 && wishes[1].hasOwnProperty("error")) {
    console.log("error getting wishes");
    return;
  }

  $("#wish-previews").empty();
  $("#match-title").text("View all wishes");
  $("#matches-back-btn").hide();

  //TODO: check args for this function, currently needs id
  //wishesMapUpdate(id);

  wishes.forEach(element => {

    //TODO: get number of matches for that wish
    var num_matches = 0;

    var div = document.createElement("div");
    div.setAttribute("class", "col-sm-1");
    div.setAttribute("id", "wish-"+ element.id);

    var card = document.createElement("div");
    card.setAttribute("class", "card");

    var cardBody = document.createElement("div");
    cardBody.setAttribute("class", "card-body");

    var cardTitle = document.createElement("h5");
    cardTitle.setAttribute("class", "card-title");
    cardTitle.innerHTML = "<span class=\"badge badge-success\">" + num_matches + "</span>";
    
    var cardText = document.createElement("p");
    cardText.setAttribute("class", "card-text");
    cardText.innerHTML = element.reason;

    var btns = document.createElement("div");
    btns.setAttribute("class", "btn-group");

    var view = document.createElement("button");
    view.innerHTML = "View";
    view.setAttribute("onclick", "getAllSuggestionsFromWish(" + element.id + ", showMatches)");
    view.setAttribute("class", "btn btn-success");

    var remove = document.createElement("button");
    remove.innerHTML = "Remove";
    remove.setAttribute("onclick", "removeWishConfirmation(" + element.id + ")");
    remove.setAttribute("class", "btn btn-danger");

    cardBody.append(cardTitle);
    cardBody.append(cardText);
    btns.append(view);
    btns.append(remove);
    cardBody.append(btns);
    card.append(cardBody);
    div.append(card);
    $("#wish-previews").append(div);
  });
}

function hideMatches() {
  $("#match-previews").empty();
  $("#matches-back-btn").hide();
  $("#match-previews").hide();
  $("#match-title").text("View all wishes");
  $("#wish-previews").show();

  updateMap();
}

function showMatches(matches) {
  console.log("Matches: \n"+ JSON.stringify(matches));
  if (matches.length>0 && matches[1].hasOwnProperty("error")) {
    console.log("error getting matches");
    return;
  }

  $("#match-title").text("View all matches for your wish");
  $("#matches-back-btn").show();

  matches.forEach(element => {

    var div = document.createElement("div");
    div.setAttribute("class", "col-sm-1");

    var card = document.createElement("div");
    card.setAttribute("class", "card");

    var cardHeader = document.createElement("div");
    cardHeader.setAttribute("class", "card-header");
    cardHeader.innerHTML = element.person;
    
    var list = document.createElement("ul");
    list.setAttribute("class", "list-group list-group-flush");

    list.append(createLI("Carbon Saving", "???", ""));
    list.append(createLI("City", "???", ""));
    list.append(createLI("Dates", "???", ""));

    var btn = document.createElement("button");
    btn.setAttribute("class", "btn btn-success");
    btn.setAttribute("onclick", "acceptMatchConfirmation("+element.id+", " + id+")");
    btn.innerText = "Accept";

    card.append(cardHeader);
    card.append(list);
    div.append(card);
    $("#match-previews").append(div);
  });

  $("#wish-previews").hide();
  $("#match-previews").show();
}

function showCarbonDetails() {
  var details = getCarbonDetails();

  var div1 = document.createElement("div");
  div1.setAttribute("class", "modal");
  div1.setAttribute("id", "carbon-details");

  var div2  = document.createElement("div");
  div2.setAttribute("class", "modal-dialog modal-dialog-centered");

  var divContent = document.createElement("div");
  divContent.setAttribute("class", "modal-content");

  var divHeader = document.createElement("div");
  divHeader.setAttribute("class", "modal-header");

  var title = document.createElement("h5");
  title.setAttribute("class", "modal-title");
  title.innerText = "Your Carbon Savings";

  var btnX =document.createElement("button");
  btnX.setAttribute("class", "close");
  btnX.setAttribute("onclick", "$('#carbon-details').remove()");

  var span = document.createElement("span");
  span.setAttribute("aria-hidden", "true");
  span.innerHTML = "&times";

  var divBody = document.createElement("div");
  divBody.setAttribute("class", "modal-body");

  //TODO add stuff to div body

  btnX.append(span);
  divHeader.append(title);
  divHeader.append(btnX);

  divContent.append(divHeader);
  divContent.append(divBody);

  div2.append(divContent);
  div1.append(div2);

  $("body").prepend(div1);
  $("#carbon-details").show();
}

function showDefaultTravel() {
  $("#travel-add").hide();
  $("#travel-default").show();
}

function makeDefaultTravel(travels) {
  console.log("Travels: \n"+ JSON.stringify(travels))
  $("#travel-default").empty();

  var btnAdd = document.createElement("button");
  btnAdd.setAttribute("class", "btn btn-success");
  btnAdd.setAttribute("onclick", "showAddTravel()");
  btnAdd.innerText = "Add new travel";

  $("#travel-default").append(btnAdd);

  travels.forEach(element => {

    var div = document.createElement("div");
    div.setAttribute("class", "col-sm-1");
    div.setAttribute("id", "travel-"+element.id);

    var card = document.createElement("div");
    card.setAttribute("class", "card");
    
    var list = document.createElement("ul");
    list.setAttribute("class", "list-group list-group-flush");

    var footer = document.createElement("div");
    footer.setAttribute("class", "card-footer");

    var btnGroup = document.createElement("div");
    btnGroup.setAttribute("class", "btn-group");

    var btnEdit = document.createElement("button");
    btnEdit.setAttribute("class", "btn btn-primary");
    btnEdit.setAttribute("onclick", "getTravelFromID("+element.id+", showEditTravel)");
    btnEdit.innerText = "Edit";

    var btnRemove = document.createElement("button");
    btnRemove.setAttribute("class", "btn btn-danger");
    btnRemove.setAttribute("onclick", "removeTravelConfirmation("+element.id+")");
    btnRemove.innerText = "Remove";

    var start = new Date(element.startTime * 1000);
    var end = new Date(element.endTime * 1000);

    list.append(createLI("City", element.city));
    list.append(createLI("Country", element.country));
    list.append(createLI("Start", start.toDateString()));
    list.append(createLI("End", end.toDateString()));

    btnGroup.append(btnEdit);
    btnGroup.append(btnRemove);
    footer.append(btnGroup);

    card.append(list);
    card.append(footer);
    div.append(card);

    $("#travel-default").append(div);
  });
}

function showAddTravel() {
  $("#travel-default").hide();
  $("#travel-add").show();
  $("#travel-btn").attr("onclick", "submitTravelNew()");
}

function showEditTravel(travel) {
  $("#travel-default").hide();
  $("#travel-add").show();

  var textAttrs = ["org", "searchbox"];
  var textVals = [travel.org, travel.city + ", "+ travel.country];
  for (var i=0; i<2; i++) {
    $("#"+textAttrs[i]+"-travel").val(textVals[i]);
  }

  var dateAttrs = ["start", "end"];
  var dateVals = [new Date(element.startTime * 1000), new Date(element.endTime * 1000)];
  for (var i=0; i<2; i++) {
    $("#"+dateAttrs[i]+"-date-travel").val(dateVals[i]);
  }

  $("#travel-btn").attr("onclick", "submitTravelEdit("+id+")");
}

function removeTravelConfirmation(id) {
  var dialog = createDialog("confirm-removal", 
    "Remove Travel", 
    "Would you like to permanently delete this travel item?", 
    "deleteTravel("+id+")");

  $("body").append(dialog);
  $("#confirm-removal").show();
}

function removeWishConfirmation(id) {
  var dialog = createDialog("confirm-removal", 
    "Remove Wish", 
    "Would you like to permanently delete this wish?", 
    "deleteWish("+id+")");

  $("body").append(dialog);
  $("#confirm-removal").show();
}

function acceptMatchConfirmation(match_id, wish_id) {
  var dialog = createDialog("confirm-removal", 
    "Accept Match", 
    "Would you like to accept this match? It will also permenantly delete the corresponding wish.", 
    "acceptMatch("+match_id+", "+wish_id+")");

  $("body").append(dialog);
  $("#confirm-removal").show();
}

function createDialog(dialogID, dialogTitle, dialogQuestion, dialogOK) {
  var div1 = document.createElement("div");
  div1.setAttribute("class", "modal");
  div1.setAttribute("id", dialogID);

  var div2  = document.createElement("div");
  div2.setAttribute("class", "modal-dialog");

  var divContent = document.createElement("div");
  divContent.setAttribute("class", "modal-content");

  var divHeader = document.createElement("div");
  divHeader.setAttribute("class", "modal-header");

  var title = document.createElement("h5");
  title.setAttribute("class", "modal-title");
  title.innerText = dialogTitle;

  var btnX =document.createElement("button");
  btnX.setAttribute("class", "close");
  btnX.setAttribute("onclick", "$('#"+dialogID + "').remove()");

  var span = document.createElement("span");
  span.setAttribute("aria-hidden", "true");
  span.innerHTML = "&times";

  var divBody = document.createElement("div");
  divBody.setAttribute("class", "modal-body");

  var p = document.createElement("p");
  p.innerText = dialogQuestion;

  var divFooter = document.createElement("div");
  divFooter.setAttribute("class", "modal-footer");

  var btnOK = document.createElement("button");
  btnOK.setAttribute("class", "btn btn-primary");
  btnOK.setAttribute("onclick", dialogOK);
  btnOK.innerText = "OK";

  var btnCancel = document.createElement("button");
  btnCancel.setAttribute("class", "btn btn-secondary");
  btnCancel.setAttribute("onclick", "$('#"+dialogID + "').remove()");
  btnCancel.innerText = "Cancel";

  btnX.append(span);
  divHeader.append(title);
  divHeader.append(btnX);

  divBody.append(p);

  divFooter.append(btnOK);
  divFooter.append(btnCancel);

  divContent.append(divHeader);
  divContent.append(divBody);
  divContent.append(divFooter);

  div2.append(divContent);
  div1.append(div2);

  return div1;
}

function createLI(title, value) {
  var li = document.createElement("li");
  li.setAttribute("class", "list-group-item");
  li.innerText = title + ": \n" + value;
  return li;
}