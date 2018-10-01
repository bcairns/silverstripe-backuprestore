<div id="backuprestore-cms-content" class="flexbox-area-grow fill-height cms-content cms-tabset $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content">

    <div class="cms-content-header vertical-align-items">
        <% with $EditForm %>
            <div class="cms-content-header-info vertical-align-items">
                <% include SilverStripe\\Admin\\BackLink_Button %>
                <% with $Controller %>
                    <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
                <% end_with %>
            </div>
        <% end_with %>
    </div>

    <div class="flexbox-area-grow cms-content-fields ui-widget-content cms-panel-padded" data-layout-type="border">

        <div class="panel panel--padded panel--scrollable flexbox-area-grow fill-height flexbox-display cms-content-view">

            <fieldset class="field form-group">
                <form method="POST" action="admin/backuprestore/backup">
                    <h2>Backup</h2>
                    <p>This will download a backup copy of the database.</p>
                    <button>Download Backup File</button>
                </form>
            </fieldset>

            <fieldset class="field form-group">
                <form method="POST" action="admin/backuprestore/restore" enctype="multipart/form-data">

                    <h2>Restore</h2>

                    <% if $IsLive %>
                        <p class="message warning livewarning"><strong>CRITICAL WARNING:</strong> Do not overwrite Live database unless you are 100% sure!</p>
                    <% end_if %>

                    <% if $RestoreMessage %>
                        <p class="message $RestoreMessage.Status">$RestoreMessage.Message</p>
                    <% end_if %>

                    <p>This will upload a backup copy of the database, <strong>completely overwriting the database on the current site</strong>.</p>
                    <p>Always have a backup of your current DB before overwriting it.</p>
                    <p><input type="file" name="upload" /></p>
                    <button>Upload Backup File</button>
                </form>
            </fieldset>


        </div>


    </div>

</div>



<%--
<div id="backuprestore-cms-content" class="cms-content center cms-tabset $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content">

	<div class="cms-content-header north">
		<% with $EditForm %>
			<div class="cms-content-header-info">

                <% include SilverStripe\\Admin\\BackLink_Button %>
                <% with $Controller %>
                    <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
                <% end_with %>

                --%>
<%--<% include BackLink_Button %>--%><%--

				--%>
<%--<% with $Controller %>--%><%--

					--%>
<%--<% include CMSBreadcrumbs %>--%><%--

				--%>
<%--<% end_with %>--%><%--

			</div>
		<% end_with %>
	</div>

	<div class="cms-content-fields center ui-widget-content" data-layout-type="border">


		<fieldset class="field">
			<form class="cms-panel-padded" method="POST" action="admin/backuprestore/backup">
				<h2>Backup</h2>
				<p>This will download a backup copy of the database.</p>
				<button>Download Backup File</button>
			</form>
		</fieldset>

		<fieldset class="field">
			<form class="cms-panel-padded" method="POST" action="admin/backuprestore/restore" enctype="multipart/form-data">

				<h2>Restore</h2>

				<% if $IsLive %>
					<p class="message warning livewarning"><strong>CRITICAL WARNING:</strong> Do not overwrite Live database unless you are 100% sure!</p>
				<% end_if %>

				<% if $RestoreMessage %>
					<p class="message $RestoreMessage.Status">$RestoreMessage.Message</p>
				<% end_if %>

				<p>This will upload a backup copy of the database, <strong>completely overwriting the database on the current site</strong>.</p>
				<p>Always have a backup of your current DB before overwriting it.</p>
				<p><input type="file" name="upload" /></p>
				<button>Upload Backup File</button>
			</form>
		</fieldset>

	</div>

</div>
--%>
