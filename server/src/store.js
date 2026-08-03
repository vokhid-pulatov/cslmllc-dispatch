import pg from 'pg';
const demo={carriers:[],loads:[]};
const pool=process.env.DATABASE_URL?new pg.Pool({connectionString:process.env.DATABASE_URL}):null;
const id=()=>crypto.randomUUID();
export const store={
 async list(type){if(!pool)return demo[type]; const {rows}=await pool.query(`SELECT * FROM ${type} ORDER BY created_at DESC`);return rows},
 async createCarrier(x){if(!pool){const row={id:id(),...x,created_at:new Date().toISOString()};demo.carriers.unshift(row);return row} const q='INSERT INTO carriers(company_name,contact_name,phone,email,mc_number,equipment,status) VALUES($1,$2,$3,$4,$5,$6,$7) RETURNING *';return (await pool.query(q,[x.company_name,x.contact_name||'',x.phone||'',x.email||'',x.mc_number||'',x.equipment||'Dry Van',x.status||'prospect'])).rows[0]},
 async createLoad(x){if(!pool){const row={id:id(),...x,gross_pay:Number(x.gross_pay||0),fee_percent:Number(x.fee_percent||0),created_at:new Date().toISOString()};demo.loads.unshift(row);return row} const q='INSERT INTO loads(carrier_id,load_number,broker,origin,destination,pickup_at,delivery_at,gross_pay,fee_percent,status) VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) RETURNING *';return (await pool.query(q,[x.carrier_id||null,x.load_number,x.broker||'',x.origin,x.destination,x.pickup_at||null,x.delivery_at||null,x.gross_pay||0,x.fee_percent||0,x.status||'planned'])).rows[0]},
 async updateLoad(id,x){if(!pool){const row=demo.loads.find(v=>v.id===id);if(!row)return null;Object.assign(row,x);return row} const allowed=['status','gross_pay','fee_percent','broker','pickup_at','delivery_at'];const keys=allowed.filter(k=>x[k]!==undefined);if(!keys.length)return null;const vals=keys.map(k=>x[k]);const sets=keys.map((k,i)=>`${k}=$${i+1}`).join(',');return (await pool.query(`UPDATE loads SET ${sets} WHERE id=$${keys.length+1} RETURNING *`,[...vals,id])).rows[0]||null}
};
