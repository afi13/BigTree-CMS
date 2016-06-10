<?php
	namespace BigTree;
	
	$groups = $admin->getModuleGroups("name ASC");
	
	// Stop notices
	$gbp = array();
	$name = $route = $group_new = $group_existing = $table = $class = "";
	$icon = "gear";
	if (isset($_SESSION["bigtree_admin"]["saved"])) {
		Globalize::arrayObject($_SESSION["bigtree_admin"]["saved"],"htmlspecialchars");
		unset($_SESSION["bigtree_admin"]["saved"]);
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/create/" class="module">
		<section>
			<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			<div class="contain">
				<div class="left">
					<fieldset>
						<label class="required"><?=Text::translate("Name")?></label>
						<input name="name" class="required" type="text" value="<?=$name?>" />
					</fieldset>
				</div>
				<div class="right">
					<fieldset<?php if (isset($_GET["error"])) { ?> class="form_error"<?php } ?>>
						<label><?=Text::translate('Route <small>(must be unique, auto generated if left blank, valid chars: alphanumeric and "-")</small>')?></label>
						<input name="route" type="text" value="<?=$route?>" />
					</fieldset>
				</div>
			</div>
			<fieldset class="developer_module_group">
				<label><?=Text::translate("Group <small>(if a new group name is chosen, the select box is ignored)</small>")?></label> 
				<input name="group_new" type="text" placeholder="<?=Text::translate("New Group", true)?>" value="<?=$group_new?>" /><span><?=Text::translate("OR")?></span> 
				<select name="group_existing">
					<option value=""></option>
					<?php foreach ($groups as $group) { ?>
					<option value="<?=$group["id"]?>"<?php if ($group_existing == $group["id"]) { ?> selected="selected"<?php } ?>><?=$group["name"]?></option>
					<?php } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset>
					<label><?=Text::translate("Related Table")?></label>
					<select name="table" id="rel_table">
						<option></option>
						<?php SQL::drawTableSelectOptions($table) ?>
					</select>
				</fieldset>
				<fieldset>
					<label class="required"><?=Text::translate("Class Name <small>(will create a class file in custom/inc/modules/)</small>")?></label>
					<input name="class" type="text" value="<?=$class?>" />
				</fieldset>
			</div>
			
			<br class="clear" />
			<fieldset>
		        <label class="required"><?=Text::translate("Icon")?></label>
		        <input type="hidden" name="icon" id="selected_icon" value="<?=$icon?>" />
		        <ul class="developer_icon_list">
		        	<?php foreach (Module::$IconClasses as $class) { ?>
		        	<li>
		        		<a href="#<?=$class?>"<?php if ($class == "gear") { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
		        	</li>
		        	<?php } ?>
		        </ul>
		    </fieldset>

			<fieldset class="left last">
				<input type="checkbox" name="gbp[enabled]" id="gbp_on" <?php if (isset($gbp["enabled"]) && $gbp["enabled"]) { ?>checked="checked" <?php } ?><?php if ($developer_only) { ?>disabled="disabled"<?php } ?> />
				<label class="for_checkbox"><?=Text::translate("Enable Advanced Permissions")?></label>
			</fieldset>
			<fieldset class="right last">
				<input type="checkbox" name="developer_only" id="developer_only" <?php if ($developer_only) { ?>checked="checked" <?php } ?>/>
				<label class="for_checkbox"><?=Text::translate("Limit Access to Developers")?></label>
			</fieldset>
		</section>
		<?php include Router::getIncludePath("admin/modules/developer/modules/_gbp.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<?php include Router::getIncludePath("admin/modules/developer/modules/_js.php") ?>