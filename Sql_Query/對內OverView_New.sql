SELECT company as Client, tab_2.model as Model, tab_1.Network, Count(*) as Total, 
	COUNT(CASE 
			WHEN a_lcm_pw = 0
			AND a_kiosk_mcb = 0
            AND a_failover = 0
            AND a_lcm_pw = 0
            AND a_fan = 0
            AND a_thermal = 0
            AND a_lan_switch_pw = 0
            AND a_flood = 0
            AND s_door = 0 
            AND a_lcm_mount = 0
            AND a_fan_1 = 0 THEN 1 END) AS Normal,
	COUNT(CASE WHEN a_kiosk_mcb = 1 THEN 1 END) AS MCB_Non_paired,
	COUNT(CASE WHEN a_lcm_pw = 1 THEN 1 END) AS LCM,
    COUNT(CASE WHEN a_player_pw = 1 THEN 1 END) AS Player,
    COUNT(CASE WHEN a_failover = 1 THEN 1 END) AS Input_Source_issue,
    COUNT(CASE WHEN a_fan = 1 THEN 1 END) AS Fan,
    COUNT(CASE WHEN a_thermal = 1 THEN 1 END) AS Thermal,
    COUNT(CASE WHEN a_lan_switch_pw = 1 THEN 1 END) AS LAN_switch,
    COUNT(CASE WHEN a_flood = 1 THEN 1 END) AS Flood,
    COUNT(CASE WHEN s_door = 1 THEN 1 END) AS Door,
    COUNT(CASE WHEN a_lcm_mount = 1 THEN 1 END) AS LCM_mount_error,
    -- COUNT(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(alarm_tags, '$.fan_ad_v1')) = 1 THEN 1 END) AS `Fan(Adv.)`
    COUNT(CASE WHEN a_fan_1 = 1 THEN 1 END) AS `Fan(Adv.)`
FROM
(
SELECT dnm.*, mcb_id, b.*, 
		IFNULL(dn.name, '') as Network
FROM displayer_network_mem as dnm
INNER JOIN displayer_network_mcb as dnmc ON dnm.net_uuid = dnmc.net_uuid
INNER JOIN displayer_network as dn ON dnm.net_uuid = dn.uuid
INNER JOIN displayer_realtime as b ON dnmc.mcb_id = b.id
WHERE mem_id = 38) as tab_1
INNER JOIN 
(
SELECT a.id, model,
		name AS company,
		a.status
	FROM displayer AS a
    INNER JOIN dynascan365_client.company AS b ON a.belong_to = b.id
    ) as tab_2 ON tab_1.mcb_id = tab_2.id
	GROUP BY Client, Model, Network
    ORDER BY Client