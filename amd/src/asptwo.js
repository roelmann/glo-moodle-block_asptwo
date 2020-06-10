define(['jquery'], function($) {

    return {
        init: function (role) {

            $(document).ready(function() {
                var linkcode = document.getElementById("id_cmidnumber").value;

                if (role !== "admin") {
                    if (linkcode.length > 0) {

                        var element = document.getElementById("page-mod-assign-mod");
                        element.classList.add("ASPCodeAdded");
                        element.classList.add(role);

                    }
                }

            });

        }
    };
});
