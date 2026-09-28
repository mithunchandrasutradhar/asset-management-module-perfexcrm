<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php $this->load->view(AMS_MODULE_NAME . '/setup/_tabs', ['active' => 'settings_' . $tab]); ?>
                <?= form_open(admin_url('asset_management/configuration/index/' . $tab), ['id' => 'settings-form']); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <?php $this->load->view(AMS_MODULE_NAME . '/' . $tabs[$tab]['view']); ?>
                    </div>
                    <?php if ($canEdit) { ?>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?= _l('settings_save'); ?></button>
                    </div>
                    <?php } ?>
                </div>
                <?= form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<?php if (! $canEdit) { ?>
<script>
    $('#settings-form').find('input, select, textarea, button').prop('disabled', true);
</script>
<?php } ?>
</body>
</html>
