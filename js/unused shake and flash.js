
 triggerAnimation(scoreboard, "shake", 1500);
        triggerAnimation(scoreboard, "flash", 100);



function triggerShakeOnButtons() {
    document.querySelectorAll(".button").forEach(button => {
        triggerShake(button);
    });
}
// Function to handle shake effect
function triggerShake(element) {
    element.classList.remove("shake");
    void element.offsetWidth; // Force reflow (triggers re-render)
    element.classList.add("shake");

    setTimeout(() => {
        element.classList.remove("shake");
    }, 500);
}


function triggerAnimation(element, animationClass, duration) {
    console.log(`Triggering ${animationClass}`);
    element.classList.remove(animationClass);
    void element.offsetWidth; // Force reflow
    element.classList.add(animationClass);

    setTimeout(() => {
        element.classList.remove(animationClass);
    }, duration);
}
