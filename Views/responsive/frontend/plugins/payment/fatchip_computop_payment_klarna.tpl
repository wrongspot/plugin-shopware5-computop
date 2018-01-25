    {* The main container for filling in the birthday field *}
    <div class="fatchip-computop-payment-klarna-form payment--form-group">

        {if !$data.phone}
            {block name="frontend_checkout_payment_fatchip_computop_phone_label"}
                <p class="none">
                    <label for="fatchip_computop_klarna_phone">{s name='klarnaPhoneLabel'}Telefonnummer{/s}</label>
                </p>
            {/block}

            {block name="frontend_checkout_payment_payone_phone_input"}
                <input name="FatchipComputopPaymentData[fatchip_computop_klarna_phone]" type="text"
                       id="fatchip_computop_klarna_phone"
                       class="payment--field is--required{if $error_flags.fatchip_computop__klarna_phone} has--error{/if}"
                       placeholder="{s name='klarnaPhoneNumber'}Telefonnummer{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}"
                       {if $payment_mean.id == $form_data.payment}required="required" aria-required="true"{/if}
                       value="{$data.phone}"
                        />
            {/block}
        {/if}

        {if !$data.birthday || $data.birthday == '00' }
            {* The main form field table *}
            {block name="frontend_checkout_payment_fatchip_computop_birthday_label"}
                <p class="none">
                    <label for="fatchip_computop_klarna_birthday">{s name='birthdate'}Bitte geben Sie Ihr Geburtsdatum an:{/s}</label>
                </p>
            {/block}

            <div class="select-field">
                {block name="frontend_checkout_payment_fatchip_computop_birthday_day_input"}
                    <select name="FatchipComputopPaymentData[fatchip_computop_klarna_birthday]"
                            id="fatchip_computop_klarna_birthday"
                            class="is--required"
                            {if $payment_mean.id == $form_data.payment}required="required" aria-required="true"{/if}
                    >
                        <option disabled="disabled" value="">--</option>
                        {section name="birthdate" start=1 loop=32 step=1}
                            {$isSelected = $smarty.section.birthdate.index == $data.birthday}
                            <option value="{$smarty.section.birthdate.index}" {if $isSelected}selected{/if}>
                                {$smarty.section.birthdate.index}
                            </option>
                        {/section}
                    </select>
                {/block}
            </div>

            <div class="select-field">
                {block name="frontend_checkout_payment_fatchip_computop_birthday_birthday_month_input"}
                    <select name="FatchipComputopPaymentData[fatchip_computop_klarna_birthmonth]"
                            id="fatchip_computop_klarna_birthmonth"
                            class="is--required"
                            {if $payment_mean.id == $form_data.payment}required="required" aria-required="true"{/if}
                    >
                        <option disabled="disabled" value="">-</option>
                        {section name="birthmonth" start=1 loop=13 step=1}
                            {$isSelected = $smarty.section.birthmonth.index == $data.birthmonth}
                            <option value="{$smarty.section.birthmonth.index}" {if $isSelected}selected{/if}>
                                {$smarty.section.birthmonth.index}
                            </option>
                        {/section}
                    </select>
                {/block}
            </div>

            <div class="select-field">
                {block name="frontend_checkout_payment_fatchip_computop_birthday_year_input"}
                    <select name="FatchipComputopPaymentData[fatchip_computop_klarna_birthyear]"
                            id="fatchip_computop_klarna_birthyear"
                            class="is--required"
                            {if $payment_mean.id == $form_data.payment}required="required" aria-required="true"{/if}
                    >
                        <option disabled="disabled" value="">----</option>
                        {section name="birthyear" loop=2000 max=100 step=-1}
                            {$isSelected = $smarty.section.birthyear.index == $data.birthyear}
                            <option value="{$smarty.section.birthyear.index}" {if $isSelected}selected{/if}>
                                {$smarty.section.birthyear.index}
                            </option>
                        {/section}
                    </select>
                {/block}
            </div>
        {/if}
    </div>