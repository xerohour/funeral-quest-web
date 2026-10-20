window.RufflePlayer = window.RufflePlayer || {};
window.RufflePlayer.config = {
  autoplay: "on",
  backgroundColor: "#000000",
  allowScriptAccess: true,
  urlRewriteRules: [[/^\s*javascript:[\s\S]*$/i, "about:blank"]],
  unmuteOverlay: "hidden",
  splashScreen: false,
  warnOnUnsupportedContent: false,
};

const player = window.RufflePlayer.newest().createPlayer();
player.style.width = "100%";
player.style.height = "100%";
document.querySelector("#stage").append(player);
player.load({ url: "/flash/welcome.swf" });
