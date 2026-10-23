rspamd_config.AUTHENTICATED_USER = {
	callback = function(task)
		local uname = task:get_user()
		if uname then
			return 1
		end
	end
}

local lua_mime = require "lua_mime"
local lua_util = require "lua_util"
local rspamd_logger = require "rspamd_logger"
local feedback_ids = rspamd_config:add_map{
	type = 'map',
	url = 'https://mail.danwin1210.de/rspamd_feedback_ids.php?token=CHANGE_ME_TO_A_LONG_RANDOM_SECRET',
	description = 'Google Feedback Loop sender IDs',
}

if not feedback_ids then
	error('cannot load Google Feedback Loop sender ID map')
end

local local_domains = rspamd_config:add_map{
	type = 'map',
	url = 'https://mail.danwin1210.de/rspamd_local_domains.php?token=CHANGE_ME_TO_A_LONG_RANDOM_SECRET',
	description = 'Hosted mail domains',
}

if not local_domains then
	error('cannot load hosted mail domains map')
end

rspamd_config:register_symbol{
	name = 'GOOGLE_FEEDBACK_ID',
	type = 'prefilter',
	priority = lua_util.symbols_priorities.top,
	flags = 'empty,nostat',
	score = 0.0,
	callback = function(task)
		local user = task:get_user()
		if not user then
			return
		end

		local feedback_id = feedback_ids:get_key(user:lower())
		if not feedback_id then
			rspamd_logger.errx(task, 'no Google Feedback Loop ID found for authenticated user %s', user)
			return
		end
		if #feedback_id < 5 or #feedback_id > 15 or not feedback_id:match('^[a-f0-9]+$') then
			rspamd_logger.errx(task, 'invalid Google Feedback Loop ID for authenticated user %s', user)
			return
		end

		lua_mime.modify_headers(task, {
			remove = { ['Feedback-ID'] = 0 },
			add = {
				['Feedback-ID'] = {
					order = 1,
					value = ':::' .. feedback_id,
				},
			},
			order = { 'Feedback-ID' },
		})
	end,
}

rspamd_config:register_symbol{
	name = 'ONION_SENDER_RECIPIENT_POLICY',
	type = 'prefilter',
	priority = lua_util.symbols_priorities.top,
	flags = 'empty,nostat',
	score = 0.0,
	callback = function(task)
		local function is_onion_address(address)
			local domain = address and address.domain
			if not domain then
				return false
			end
			domain = domain:lower():gsub('%.$', '')
			return domain:match('%.onion$') ~= nil
		end

		local onion_sender = false
		for _, address_type in ipairs({ 'smtp', 'mime' }) do
			local senders = task:get_from(address_type) or {}
			for _, sender in ipairs(senders) do
				if is_onion_address(sender) then
					onion_sender = true
					break
				end
			end
			if onion_sender then
				break
			end
		end

		if not onion_sender then
			return
		end

		local recipients = task:get_recipients('smtp') or {}
		if #recipients == 0 then
			return
		end
		for _, recipient in ipairs(recipients) do
			if not is_onion_address(recipient) then
				task:set_pre_result(
					'reject',
					'Messages from .onion senders may only be sent to .onion recipients',
					'ONION_SENDER_RECIPIENT_POLICY'
				)
				return
			end
		end
	end,
}

rspamd_config:register_symbol{
	name = 'LOCAL_DOMAIN_AUTH_POLICY',
	type = 'postfilter',
	priority = lua_util.symbols_priorities.high,
	flags = 'empty,nostat',
	score = 0.0,
	callback = function(task)
		if task:get_user() then
			return
		end

		local ip = task:get_ip()
		if ip and ip:is_local() then
			return
		end

		local spf_allowed = task:get_symbol('R_SPF_ALLOW') ~= nil
		local dmarc_allowed = task:get_symbol('DMARC_POLICY_ALLOW') ~= nil
		local dkim_allowed = task:get_symbol('R_DKIM_ALLOW') ~= nil
		local dkim_aligned = task:get_symbol('R_DKIM_ALIGNED') ~= nil
		local smtp_senders = task:get_from('smtp') or {}
		local mime_senders = task:get_from('mime') or {}
		local local_envelope_sender = false
		local local_header_sender = false
		local matching_senders = false
		for _, sender in ipairs(mime_senders) do
			local domain = sender.domain
			if domain then
				domain = domain:lower():gsub('%.$', '')
				if local_domains:get_key(domain) then
					local_header_sender = true
					break
				end
			end
		end

		for _, sender in ipairs(smtp_senders) do
			local domain = sender.domain
			if domain then
				domain = domain:lower():gsub('%.$', '')
				if local_domains:get_key(domain) then
					local_envelope_sender = true
					break
				end
			end
		end

		for _, smtp_sender in ipairs(smtp_senders) do
			for _, mime_sender in ipairs(mime_senders) do
				if smtp_sender.domain and mime_sender.domain
					and smtp_sender.domain:lower():gsub('%.$', '') == mime_sender.domain:lower():gsub('%.$', '') then
					matching_senders = true
					break
				end
			end
			if matching_senders then
				break
			end
		end

		local authenticated_envelope = not local_envelope_sender or spf_allowed or ( ( dmarc_allowed or ( dkim_aligned and dkim_allowed ) ) and matching_senders )
		local authenticated_header = not local_header_sender or dmarc_allowed or ( dkim_aligned and dkim_allowed ) or ( matching_senders and spf_allowed )
		if not authenticated_envelope or not authenticated_header then
			task:set_pre_result(
				'reject',
				'Messages using hosted sender domains require SPF, DKIM, or DMARC authentication',
				'LOCAL_DOMAIN_AUTH_POLICY'
			)
			return
		end
	end,
}
