package main.java.add;

import data.Location;
import cost.Cost;

import java.sql.ResultSet;
import java.sql.SQLException;

public class AddedAggregatedOrgPresence {
    public static void add(String table, int key) throws SQLException {
        //Get organization presence details
        ResultSet rsOrgPres = Add.dbCon.executeQuery("SELECT * FROM aggregate_org_presences WHERE table_id = " + key + " AND type = '" + table + "'");
        if (!rsOrgPres.next())
            throw new InternalError("key " + key + ": does not exist in specified table (" + table + ")");
        int OPid = rsOrgPres.getInt("id");
        int OPstartTime = rsOrgPres.getInt("startTime");
        int OPendtTime = rsOrgPres.getInt("endTime");
        int OPloc_id = rsOrgPres.getInt("loc_id");
        Location OPlocation = AddedHelperFunctions.getLocationById(OPloc_id);
        if (rsOrgPres.next())
            throw new InternalError("key " + key + ": identifies multiple entries in specified table (" + table + ")");
        //iterate on wish constraints where this organization is needed
        ResultSet rsWishes = Add.dbCon.executeQuery("SELECT * FROM wish_constraints WHERE type = 'ORGANISATION' AND org_id = " + OPid);
        while (rsWishes.next()) {
            //if wish has location constraints, and no-one matches this org presence, then no suggestion can be produced.
            if (Add.dbCon.executeQuery("SELECT * FROM wish_constraints WHERE type = 'LOCATION' AND wish_id = " + rsWishes.getInt("id")).next()) {
                //has location constraints
                if (!Add.dbCon.executeQuery("SELECT * FROM wish_constraints WHERE type = 'LOCATION' AND wish_id = " + rsWishes.getInt("id") + " AND loc_id= " + OPloc_id).next()) {
                    //no one of them matches
                    continue;
                }
            }
            //try to match with all the UnepPresences
            ResultSet rsUnepPresences = Add.dbCon.executeQuery("SELECT * FROM aggregate_unep_presences");
            while (rsUnepPresences.next()) {
                double cost = Cost.calculateCost(
                        AddedHelperFunctions.smallestTimeDeltaFiltered(
                                Add.dbCon.executeQuery("SELECT * FROM wish_constraints WHERE type='TIME' AND wish_id=" + rsWishes.getInt("id")),
                                OPstartTime,
                                OPendtTime,
                                rsUnepPresences.getInt("startTime"),
                                rsUnepPresences.getInt("endTime")
                        ),
                        OPlocation,
                        AddedHelperFunctions.getLocationById(rsUnepPresences.getInt("loc_id")));
                //TODO: add this suggestion to table
            }
        }
    }
}
