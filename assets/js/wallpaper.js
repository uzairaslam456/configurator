(function ($) {
    $(document).ready(function () {
        $.cmToM = (s) => parseFloat(parseInt(s) / 100);
        const gcd = (a, b) => (b === 0 ? a : gcd(b, a % b));

        var wto;
        var config = {
            width: 200,
            height: 50,
            widthCM: 0,
            heightCM: 0,
            flipped: 0,
            full: 0,
            area: 0
        };

        $("input").on("input", function (e) {
            let min = $(this).data('min');
            let minWidth = $("input[name='p-width']").data('min');
            let minHeight = $("input[name='p-height']").data('min');

            clearTimeout(wto);
            wto = setTimeout(() => {
                let centiMeters = this.value;
                let content = "";
                let propName = $(this).attr("name").replace("p-", "");

                if (propName !== "width" && propName !== "height") return;

                if (centiMeters < min) {
                    centiMeters = min;
                    console.log($(this).attr("name"));

                    $(this).val(centiMeters);

                    content +=
                        `<p><small>Le tue misure sono state modificate. La dimensione minima ordinabile per la carta da parati è ${minWidth}x${minHeight}cm. Controlla e cambia le dimensioni per adattarle alla tua parete.</small></p>`;
                }

                const meters = $.cmToM(centiMeters);

                config[propName] = parseInt(centiMeters);
                $(`.pane-settings input[name="${$(this).attr("name")}"]`).val(centiMeters);

                $(`.ruler-${propName} .ruler-content`).html(`${centiMeters} cm`);

                content += !isNaN(meters) ? `${this.value}cm = ${meters}m` : "";
                $(`#${$(this).attr("name")} .feedback`).html(content);
                $("#settings-action .action-desc").html(`${config.width}  x ${config.height} cm`);

                updatePrice();
                start();
            }, 1000);
        });

        $(".p-view-tooltip").click(function (e) {
            $(this).parent().find(".p-tooltip-message").fadeToggle();
        });

        $("#start").click(function (e) {
            e.preventDefault();

            if (config.width < 30 && config.height < 30) {
                alert("Please enter wallpaper size");
                return;
            }

            $("#intial-config").fadeOut();
            $(".p-modals").css("visibility", "hidden");
            start();
        });

        var isDown = false; // whether mouse is pressed
        var startCoords = []; // 'grab' coordinates when pressing mouse
        var last = [0, 0]; // previous coordinates of mouse release
        var maxDrag = [0, 0];

        const ele = document.getElementById("cropArea");
        const banner = document.getElementById("sourceBanner");

        // $("#intial-config").fadeOut();
        // start();

        console.log(maxDrag);

        function start() {
            let sizes = cropSize(document.getElementById("sourceBanner"), config.width / config.height);

            console.log(sizes);

            maxDrag[0] = parseInt((sizes.originalWidth - sizes.width) / 2);
            maxDrag[1] = parseInt((sizes.originalHeight - sizes.height) / 2);

            let cWidth = ((config.width + 100) > sizes.originalWidth ? sizes.originalWidth : config.width) + 100;
            let cHeight = ((config.height + 100) > sizes.originalHeight ? sizes.originalHeight : config.height) + 100;

            $("#cropArea").css({
                width: cWidth + "px",
                height: cHeight + "px"
            });

            let $viewport = $("#viewport");

            $viewport.css("width", `${sizes.originalWidth + 100}px`);
            $viewport.css("height", `${sizes.originalHeight + 100}px`);

            $(".ruler-height .ruler-content").css("height", `${cHeight}px`);
            $(".ruler-width .ruler-content").css("width", `${cWidth}px`);

            $("div[data-id='quantity_field_id_0'] input[name='quantityField']").val(config.width);
            $("div[data-id='quantity_field_id_1'] input[name='quantityField']").val(config.height);
        }

        ele.onmousedown = function (e) {
            isDown = true;

            startCoords = [
                e.offsetX - last[0], // set start coordinates
                e.offsetY - last[1],
            ];

            // console.log(last);
        };

        ele.onmouseup = function (e) {
            isDown = false;

            console.log(Math.abs(last[1]));

            last = [
                last[0] <= maxDrag[0] ? e.offsetX - startCoords[0] : maxDrag[0], // set last coordinates
                last[1] <= maxDrag[1] ? e.offsetY - startCoords[1] : maxDrag[1],
            ];
        };

        ele.onmousemove = function (e) {
            if (!isDown) return; // don't pan if mouse is not pressed

            var x = e.offsetX;
            var y = e.offsetY;

            render(x - startCoords[0], y - startCoords[1]); // render to show changes
        };

        function render(x, y) {
            // if (Math.abs(x) <= maxDrag[0]) {
                banner.style.left = `${x}px`;
            // }

            // if (Math.abs(y) <= maxDrag[1]) {
                banner.style.top = `${y}px`;
            // }
        }

        $(".action-button").click(function (e) {
            closePane($(this));
        });

        $(".close-pane").click(function (e) {
            closePane($(this).parent().parent().parent().find(".action-button"));
        });

        function closePane(ref) {
            ref.toggleClass("open").find(".action-marker i").toggleClass("fa-chevron-down").toggleClass("fa-chevron-up");

            ref.parent().find(".action-pane").fadeToggle();
        }

        $(".plan").click(function (e) {
            let $radio = $(this).find('input[type="radio"]');
            let quality = $radio.val();

            $radio.prop("checked", true);

            let $actionPane = $(this).closest(".action");

            $actionPane.find(".action-desc").html(`${quality} ${$(this).find(".plan-price").html()}`);
            $(".plan").removeClass("selected");
            $(this).addClass("selected");

            closePane($actionPane.find(".action-button"));
            updatePrice();

            $("#add-to-cart").removeClass("disabled").addClass("add-to-cart");
        });

        let area = 0;
        let atc = $("#cd-add-to-cart");

        function updatePrice() {
            let $quality = $('input[name="quality[]"]:checked');
            if (!$quality.val()) return;

            config.area = $.cmToM(config.width) * $.cmToM(config.height);
            let totalPrice = config.area * parseInt($quality.closest(".plan").find(".plan-amount").data("amount"));
            let area = parseInt(config.area < 1 ? 1 : config.area);

            $(".total-price").html(`€ ${totalPrice.toFixed(2)}`);

            $('#cd_area_needed').val(area);
            $('#cd_measurement_needed').val(area);

            $('#cd_width').val(config.width < 1 ? 1 : config.width);
            $('#cd_height').val(config.height < 1 ? 1 : config.height);

            $('#cd_mirrored').val(config.flipped);
            $('#cd_whole').val(config.full);
        }

        $('#at-the-mirror').click(function (e) {
            config.flipped = !config.flipped;

            $('#sourceBanner').toggleClass('flipped');
        });

        $('#use-whole-image').click(function (e) {
            config.full = !config.full;

            $('#sourceBanner').toggleClass('use-full');
        });

        console.clear();

        $('#add-to-cart').click(function(e) {
            e.preventDefault();

            // Submitting form
            $('#cd-cart').submit();
        });

    });
})(jQuery);

/**
 * @sourceImage - Source of the image to use
 * @aspectRatio - The aspect ratio to apply
 */
function cropSize(sourceImage, aspectRatio) {
    const sourceWidth = sourceImage.clientWidth;
    const sourceHeight = sourceImage.clientHeight;

    const inputImageAspectRatio = sourceWidth / sourceHeight;

    let outputWidth = sourceWidth;
    let outputHeight = sourceHeight;

    if (inputImageAspectRatio > aspectRatio) {
        outputWidth = sourceHeight * aspectRatio;
    } else if (inputImageAspectRatio < aspectRatio) {
        outputHeight = sourceWidth / aspectRatio;
    }

    return {
        width: outputWidth,
        height: outputHeight,
        originalWidth: sourceWidth,
        originalHeight: sourceHeight,
        widthCm: parseInt(sourceWidth * 2.54 / 96),
        heightCm: parseInt(sourceHeight * 2.54 / 96)
    };
}
